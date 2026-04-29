<?php

namespace Tests\Unit\Repository;

use STS\Models\Conversation;
use STS\Models\Message;
use STS\Models\Passenger;
use STS\Models\Trip;
use STS\Models\User;
use STS\Repository\UserRepository;
use STS\Services\Notifications\Models\DatabaseNotification;
use Tests\TestCase;

class UserRepositoryTest extends TestCase
{
    private function repo(): UserRepository
    {
        return new UserRepository;
    }

    public function test_create_persists_user(): void
    {
        $attrs = User::factory()->make()->toArray();
        $attrs['email'] = 'create-'.uniqid('', true).'@example.com';

        $user = $this->repo()->create($attrs);

        $this->assertNotNull($user->id);
        $this->assertSame($attrs['email'], $user->email);
    }

    public function test_update_strips_is_admin_from_payload(): void
    {
        $user = User::factory()->create();
        $user->forceFill(['is_admin' => false])->saveQuietly();

        $this->repo()->update($user, [
            'name' => 'Updated Name',
            'is_admin' => true,
        ]);

        $user->refresh();
        $this->assertSame('Updated Name', $user->name);
        $this->assertFalse((bool) $user->is_admin);
    }

    public function test_show_nulls_private_note_and_loads_relations(): void
    {
        $user = User::factory()->create();
        $user->forceFill(['private_note' => 'secret admin note'])->saveQuietly();

        $shown = $this->repo()->show($user->id);

        $this->assertNotNull($shown);
        $this->assertNull($shown->private_note);
        // Mutation intent: preserve eager-load array in show().
        $this->assertTrue($shown->relationLoaded('accounts'));
        $this->assertTrue($shown->relationLoaded('donations'));
        $this->assertTrue($shown->relationLoaded('referencesReceived'));
        $this->assertTrue($shown->relationLoaded('cars'));
    }

    public function test_accept_terms_and_update_photo(): void
    {
        $user = User::factory()->create(['terms_and_conditions' => false]);

        $accepted = $this->repo()->acceptTerms($user);
        $this->assertNotNull($accepted);
        $this->assertSame($user->id, $accepted->id);
        $this->assertTrue($user->fresh()->terms_and_conditions);

        $updated = $this->repo()->updatePhoto($user, 'avatar-99.png');
        $this->assertNotNull($updated);
        $this->assertSame($user->id, $updated->id);
        $this->assertSame('avatar-99.png', $user->fresh()->image);
    }

    public function test_get_user_by(): void
    {
        $user = User::factory()->create(['email' => 'byemail-'.uniqid('', true).'@example.com']);

        $found = $this->repo()->getUserBy('email', $user->email);

        $this->assertNotNull($found);
        $this->assertTrue($found->is($user));
    }

    public function test_search_users_null_returns_null_and_limits_matches(): void
    {
        $this->assertNull($this->repo()->searchUsers(null));
        $this->assertNull($this->repo()->searchUsers(''));

        $needle = 'SearchNeedleXy'.substr(uniqid('', true), 0, 8);
        User::factory()->count(10)->create(['name' => $needle.' Person']);

        $rows = $this->repo()->searchUsers($needle);
        $this->assertCount(9, $rows);
    }

    public function test_search_users_matches_name_email_doc_or_phone(): void
    {
        $u1 = User::factory()->create(['name' => 'AlphaUniqueDoc', 'nro_doc' => 'DOC991122']);
        $u2 = User::factory()->create(['email' => 'beta-unique-991122@example.com']);
        $u3 = User::factory()->create(['mobile_phone' => '+5491199112233']);

        $byDoc = $this->repo()->searchUsers('991122');
        $this->assertTrue($byDoc->pluck('id')->contains($u1->id));
        $this->assertTrue($byDoc->pluck('id')->contains($u2->id));
        $this->assertTrue($byDoc->pluck('id')->contains($u3->id));
    }

    public function test_index_excludes_self_banned_inactive_and_accepted_friends(): void
    {
        $self = User::factory()->create(['name' => 'Self User', 'active' => true, 'banned' => false]);
        $friend = User::factory()->create(['name' => 'Friend User', 'active' => true, 'banned' => false]);
        $stranger = User::factory()->create(['name' => 'Stranger User', 'active' => true, 'banned' => false]);
        $inactive = User::factory()->create(['name' => 'Inactive User', 'active' => false, 'banned' => false]);

        $this->repo()->addFriend($self, $friend, 'test');

        $rows = $this->repo()->index($self, null);
        $ids = $rows->pluck('id');

        $this->assertFalse($ids->contains($self->id));
        $this->assertFalse($ids->contains($friend->id));
        $this->assertTrue($ids->contains($stranger->id));
        $this->assertFalse($ids->contains($inactive->id));
    }

    public function test_index_filters_by_search_text_and_sets_state_none(): void
    {
        $self = User::factory()->create(['name' => 'IndexSelf']);
        User::factory()->create(['name' => 'OtherPersonX', 'active' => true, 'banned' => false]);
        $match = User::factory()->create(['name' => 'FindMeZebraX', 'active' => true, 'banned' => false]);

        $rows = $this->repo()->index($self, 'ZebraX');
        $this->assertCount(1, $rows);
        $this->assertSame($match->id, $rows->first()->id);
        $this->assertSame('none', $rows->first()->state);
    }

    public function test_add_friend_and_delete_friend_sync_bidirectional_pivot(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();
        $repo = $this->repo();

        $repo->addFriend($a, $b, 'unit');

        $this->assertTrue($a->friends()->where('users.id', $b->id)->exists());
        $this->assertTrue($b->friends()->where('users.id', $a->id)->exists());

        $repo->deleteFriend($a, $b);

        $this->assertFalse($a->fresh()->friends()->where('users.id', $b->id)->exists());
        $this->assertFalse($b->fresh()->friends()->where('users.id', $a->id)->exists());
    }

    public function test_password_reset_token_round_trip(): void
    {
        $user = User::factory()->create(['email' => 'reset-'.uniqid('', true).'@example.com']);
        $token = 'tok-'.uniqid('', true);

        $this->repo()->storeResetToken($user, $token);

        $this->assertDatabaseHas('password_resets', ['email' => $user->email, 'token' => $token]);

        $resolved = $this->repo()->getUserByResetToken($token);
        $this->assertTrue($resolved->is($user));

        $last = $this->repo()->getLastPasswordReset($user->email);
        $this->assertSame($token, $last->token);

        $this->repo()->deleteResetToken('token', $token);
        $this->assertDatabaseMissing('password_resets', ['token' => $token]);
    }

    public function test_get_notifications_respects_unread_flag(): void
    {
        $user = User::factory()->create();
        DatabaseNotification::create([
            'user_id' => $user->id,
            'type' => 'Tests\\DummyNotification',
            'read_at' => null,
        ]);
        DatabaseNotification::create([
            'user_id' => $user->id,
            'type' => 'Tests\\DummyNotification2',
            'read_at' => now(),
        ]);

        $all = $this->repo()->getNotifications($user->fresh(), false);
        $this->assertCount(2, $all);

        $unread = $this->repo()->getNotifications($user->fresh(), true);
        $this->assertCount(1, $unread);
        $this->assertNull($unread->first()->read_at);
    }

    public function test_mark_notification_sets_read_at(): void
    {
        $user = User::factory()->create();
        $n = DatabaseNotification::create([
            'user_id' => $user->id,
            'type' => 'Tests\\MarkReadNotification',
            'read_at' => null,
        ]);

        $this->repo()->markNotification($n);

        $this->assertNotNull($n->fresh()->read_at);
    }

    public function test_unanswered_conversation_or_requests_by_trip(): void
    {
        $driver = User::factory()->create();
        $passenger = User::factory()->create();
        $trip = Trip::factory()->create(['user_id' => $driver->id]);
        Passenger::factory()->create([
            'trip_id' => $trip->id,
            'user_id' => $passenger->id,
            'request_state' => Passenger::STATE_PENDING,
        ]);
        $conversation = Conversation::factory()->create(['trip_id' => $trip->id]);
        Message::create([
            'user_id' => $passenger->id,
            'conversation_id' => $conversation->id,
            'text' => 'Hi',
            'estado' => Message::STATE_NOLEIDO,
        ]);

        $before = $this->repo()->unansweredConversationOrRequestsByTrip($driver->id, $trip->id);
        $this->assertSame(2, $before);

        Message::create([
            'user_id' => $driver->id,
            'conversation_id' => $conversation->id,
            'text' => 'Reply',
            'estado' => Message::STATE_NOLEIDO,
        ]);

        $after = $this->repo()->unansweredConversationOrRequestsByTrip($driver->id, $trip->id);
        $this->assertSame(1, $after);
    }
}
