<?php

namespace Tests\Unit\Repository;

use Carbon\Carbon;
use Mockery;
use Illuminate\Support\Facades\DB;
use STS\Models\Conversation;
use STS\Models\Message;
use STS\Models\User;
use STS\Repository\MessageRepository;
use Tests\TestCase;

class MessageRepositoryTest extends TestCase
{
    private function repo(): MessageRepository
    {
        return new MessageRepository;
    }

    private function makeMessage(Conversation $conversation, User $sender, string $text = 'Hello', ?Carbon $at = null): Message
    {
        $message = Message::create([
            'user_id' => $sender->id,
            'conversation_id' => $conversation->id,
            'text' => $text,
            'estado' => Message::STATE_NOLEIDO,
        ]);
        if ($at !== null) {
            $message->forceFill(['created_at' => $at, 'updated_at' => $at])->saveQuietly();
        }

        return $message->fresh();
    }

    public function test_store_persists_message(): void
    {
        $sender = User::factory()->create();
        $conversation = Conversation::factory()->create();
        $message = new Message([
            'user_id' => $sender->id,
            'conversation_id' => $conversation->id,
            'text' => 'Stored body',
            'estado' => Message::STATE_NOLEIDO,
        ]);

        $this->assertTrue($this->repo()->store($message));

        $this->assertDatabaseHas('messages', [
            'id' => $message->id,
            'conversation_id' => $conversation->id,
            'text' => 'Stored body',
        ]);
    }

    public function test_store_returns_false_when_save_fails(): void
    {
        $message = Mockery::mock(Message::class);
        $message->shouldReceive('save')->once()->andReturn(false);

        $this->assertFalse($this->repo()->store($message));
    }

    public function test_delete_returns_false_when_delete_fails(): void
    {
        $message = Mockery::mock(Message::class);
        $message->shouldReceive('delete')->once()->andReturn(false);

        $this->assertFalse($this->repo()->delete($message));
    }

    public function test_store_invokes_save(): void
    {
        // Mutation intent: preserve `return $message->save()` (~13–16 RemoveMethodCall).
        $message = Mockery::mock(Message::class);
        $message->shouldReceive('save')->once()->andReturn(true);

        $this->assertTrue($this->repo()->store($message));
    }

    public function test_delete_invokes_delete(): void
    {
        // Mutation intent: preserve `return $message->delete()` (~18–21 RemoveMethodCall).
        $message = Mockery::mock(Message::class);
        $message->shouldReceive('delete')->once()->andReturn(true);

        $this->assertTrue($this->repo()->delete($message));
    }

    public function test_delete_removes_message(): void
    {
        $sender = User::factory()->create();
        $conversation = Conversation::factory()->create();
        $message = $this->makeMessage($conversation, $sender);
        $id = $message->id;

        $this->assertTrue((bool) $this->repo()->delete($message));

        $this->assertNull(Message::query()->find($id));
    }

    public function test_get_messages_returns_empty_when_conversation_has_no_messages(): void
    {
        // Mutation intent: preserve relation query + take when zero rows (~23–34).
        $conversation = Conversation::factory()->create();

        $batch = $this->repo()->getMessages($conversation, null, 10);

        $this->assertCount(0, $batch);
    }

    public function test_get_messages_orders_newest_first_and_respects_page_size(): void
    {
        $conversation = Conversation::factory()->create();
        $sender = User::factory()->create();
        $m1 = $this->makeMessage($conversation, $sender, 'oldest', Carbon::parse('2020-01-01 10:00:00'));
        $m2 = $this->makeMessage($conversation, $sender, 'middle', Carbon::parse('2020-01-02 10:00:00'));
        $m3 = $this->makeMessage($conversation, $sender, 'newest', Carbon::parse('2020-01-03 10:00:00'));

        $batch = $this->repo()->getMessages($conversation, null, 2);

        $this->assertCount(2, $batch);
        $this->assertSame($m3->id, $batch[0]->id);
        $this->assertSame($m2->id, $batch[1]->id);

        $all = $this->repo()->getMessages($conversation, null, 10);
        $this->assertCount(3, $all);
        $this->assertSame([$m3->id, $m2->id, $m1->id], $all->pluck('id')->all());
    }

    public function test_get_messages_with_timestamp_excludes_newer_or_equal_rows(): void
    {
        $conversation = Conversation::factory()->create();
        $sender = User::factory()->create();
        $old = $this->makeMessage($conversation, $sender, 'old', Carbon::parse('2020-06-01 12:00:00'));
        $this->makeMessage($conversation, $sender, 'new', Carbon::parse('2020-06-02 12:00:00'));

        $cut = Carbon::parse('2020-06-02 12:00:00');
        $batch = $this->repo()->getMessages($conversation, $cut, 10);

        $this->assertCount(1, $batch);
        $this->assertSame($old->id, $batch->first()->id);
    }

    public function test_get_unread_messages_only_includes_messages_where_user_pivot_unread(): void
    {
        $conversation = Conversation::factory()->create();
        $sender = User::factory()->create();
        $reader = User::factory()->create();

        $unread = $this->makeMessage($conversation, $sender, 'unread');
        $read = $this->makeMessage($conversation, $sender, 'read');
        $unread->users()->attach($reader->id, ['read' => false]);
        $read->users()->attach($reader->id, ['read' => true]);

        $rows = $this->repo()->getUnreadMessages($conversation, $reader);

        $this->assertCount(1, $rows);
        $this->assertSame($unread->id, $rows->first()->id);
    }

    public function test_get_unread_messages_returns_empty_when_no_unread_for_user(): void
    {
        // Mutation intent: preserve whereHas users read=false (~37–42).
        $conversation = Conversation::factory()->create();
        $sender = User::factory()->create();
        $reader = User::factory()->create();

        $onlyRead = $this->makeMessage($conversation, $sender, 'seen');
        $onlyRead->users()->attach($reader->id, ['read' => true]);

        $this->assertCount(0, $this->repo()->getUnreadMessages($conversation, $reader));
    }

    public function test_change_message_read_state_updates_pivot(): void
    {
        $conversation = Conversation::factory()->create();
        $sender = User::factory()->create();
        $reader = User::factory()->create();
        $message = $this->makeMessage($conversation, $sender);
        $message->users()->attach($reader->id, ['read' => false]);

        $this->repo()->changeMessageReadState($message, $reader, true);

        $pivot = $message->fresh()->users()->where('user_id', $reader->id)->first()->pivot;
        $this->assertTrue((bool) $pivot->read);
    }

    public function test_create_message_read_state_attaches_user_with_read_flag(): void
    {
        $conversation = Conversation::factory()->create();
        $sender = User::factory()->create();
        $reader = User::factory()->create();
        $message = $this->makeMessage($conversation, $sender);

        $this->repo()->createMessageReadState($message, $reader, false);

        $this->assertTrue($message->users()->where('user_id', $reader->id)->exists());
        $pivot = $message->fresh()->users()->where('user_id', $reader->id)->first()->pivot;
        $this->assertFalse((bool) $pivot->read);
    }

    public function test_get_messages_unread_uses_unread_conversations_and_optional_timestamp(): void
    {
        $reader = User::factory()->create();
        $sender = User::factory()->create();

        $convUnread = Conversation::factory()->create();
        $convRead = Conversation::factory()->create();
        $reader->conversations()->attach($convUnread->id, ['read' => false]);
        $reader->conversations()->attach($convRead->id, ['read' => true]);

        $mOld = $this->makeMessage($convUnread, $sender, 'a', Carbon::parse('2019-01-01 08:00:00'));
        $mNew = $this->makeMessage($convUnread, $sender, 'b', Carbon::parse('2019-01-05 08:00:00'));

        $allUnreadConv = $this->repo()->getMessagesUnread($reader, null);
        $this->assertTrue($allUnreadConv->pluck('id')->contains($mOld->id));
        $this->assertTrue($allUnreadConv->pluck('id')->contains($mNew->id));

        $since = Carbon::parse('2019-01-03 08:00:00');
        $afterTs = $this->repo()->getMessagesUnread($reader, $since);
        $this->assertCount(1, $afterTs);
        $this->assertSame($mNew->id, $afterTs->first()->id);
    }

    public function test_get_messages_unread_returns_empty_when_user_has_no_conversations(): void
    {
        // Mutation intent: `$conversations_id` stays empty (~62–78).
        $reader = User::factory()->create();

        $this->assertCount(0, $this->repo()->getMessagesUnread($reader, null));
    }

    public function test_get_messages_unread_returns_empty_when_all_conversations_marked_read(): void
    {
        $reader = User::factory()->create();
        $sender = User::factory()->create();

        $conv = Conversation::factory()->create();
        $reader->conversations()->attach($conv->id, ['read' => true]);
        $this->makeMessage($conv, $sender, 'ignored');

        $this->assertCount(0, $this->repo()->getMessagesUnread($reader->fresh(), null));
    }

    public function test_mark_messages_sets_read_for_matching_pivot_rows(): void
    {
        $user = User::factory()->create();
        $sender = User::factory()->create();
        $conversation = Conversation::factory()->create();

        $m1 = $this->makeMessage($conversation, $sender, 'one');
        $m2 = $this->makeMessage($conversation, $sender, 'two');
        $m1->users()->attach($user->id, ['read' => false]);
        $m2->users()->attach($user->id, ['read' => false]);

        $this->repo()->markMessages($user, $conversation->id);

        $this->assertTrue((bool) DB::table('user_message_read')
            ->where('message_id', $m1->id)
            ->where('user_id', $user->id)
            ->value('read'));
        $this->assertTrue((bool) DB::table('user_message_read')
            ->where('message_id', $m2->id)
            ->where('user_id', $user->id)
            ->value('read'));
    }

    public function test_mark_messages_updates_user_message_read_updated_at(): void
    {
        // Mutation intent: preserve `'updated_at' => Carbon::Now()` in DB::table(...)->update([...]) (RemoveArrayItem on updated_at cluster).
        Carbon::setTestNow(Carbon::parse('2024-05-01 10:00:00'));

        $user = User::factory()->create();
        $sender = User::factory()->create();
        $conversation = Conversation::factory()->create();

        $m = $this->makeMessage($conversation, $sender, 'x');
        $m->users()->attach($user->id, ['read' => false]);

        Carbon::setTestNow(Carbon::parse('2024-06-01 12:00:00'));

        $this->repo()->markMessages($user, $conversation->id);

        Carbon::setTestNow();

        $this->assertSame(
            '2024-06-01 12:00:00',
            Carbon::parse(
                (string) DB::table('user_message_read')
                    ->where('message_id', $m->id)
                    ->where('user_id', $user->id)
                    ->value('updated_at')
            )->format('Y-m-d H:i:s')
        );
    }

    public function test_mark_messages_completes_when_no_message_ids_match_unread_pivot(): void
    {
        // Mutation intent: empty `pluck('id')` after `whereHas` unread=false (~83–98); bulk update must not assume non-empty ids.
        $user = User::factory()->create();
        $sender = User::factory()->create();
        $conversation = Conversation::factory()->create();
        $m = $this->makeMessage($conversation, $sender, 'already-read');
        $m->users()->attach($user->id, ['read' => true]);

        Carbon::setTestNow(Carbon::parse('2025-03-10 09:00:00'));

        $this->repo()->markMessages($user, $conversation->id);

        Carbon::setTestNow();

        $this->assertSame(1, (int) DB::table('user_message_read')
            ->where('message_id', $m->id)
            ->where('user_id', $user->id)
            ->value('read'));
    }

    public function test_change_message_read_state_persists_read_flag_in_user_message_read(): void
    {
        // Mutation intent: preserve updateExistingPivot payload `['read' => $read_state]` (RemoveArrayItem / FalseToTrue clusters).
        $conversation = Conversation::factory()->create();
        $sender = User::factory()->create();
        $reader = User::factory()->create();
        $message = $this->makeMessage($conversation, $sender);
        $message->users()->attach($reader->id, ['read' => false]);

        $this->repo()->changeMessageReadState($message, $reader, true);

        $this->assertSame(
            1,
            (int) DB::table('user_message_read')
                ->where('message_id', $message->id)
                ->where('user_id', $reader->id)
                ->value('read')
        );
    }

    public function test_create_message_read_state_inserts_read_into_user_message_read(): void
    {
        // Mutation intent: preserve attach pivot array `['read' => $read_state]` on message users relation.
        $conversation = Conversation::factory()->create();
        $sender = User::factory()->create();
        $reader = User::factory()->create();
        $message = $this->makeMessage($conversation, $sender);

        $this->repo()->createMessageReadState($message, $reader, true);

        $this->assertSame(
            1,
            (int) DB::table('user_message_read')
                ->where('message_id', $message->id)
                ->where('user_id', $reader->id)
                ->value('read')
        );
    }

    public function test_get_messages_unread_includes_conversation_when_pivot_read_is_loosely_zero(): void
    {
        // Mutation intent: preserve `$item->pivot->read == 0` — strict `=== 0` skips numeric-string pivots that still mean unread.
        $reader = User::factory()->create();
        $sender = User::factory()->create();

        $convUnread = Conversation::factory()->create();
        $reader->conversations()->attach($convUnread->id, ['read' => false]);

        DB::table('conversations_users')
            ->where('user_id', $reader->id)
            ->where('conversation_id', $convUnread->id)
            ->update(['read' => '0']);

        $reader = User::query()->findOrFail($reader->id);

        $this->makeMessage($convUnread, $sender, 'ping', Carbon::parse('2019-02-01 08:00:00'));

        $rows = $this->repo()->getMessagesUnread($reader, null);

        $this->assertSame(1, $rows->count());
    }
}
