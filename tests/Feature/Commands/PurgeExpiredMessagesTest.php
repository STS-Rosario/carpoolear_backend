<?php

namespace Tests\Feature\Commands;

use Illuminate\Support\Facades\DB;
use STS\Models\Conversation;
use STS\Models\Message;
use STS\Models\User;
use Tests\TestCase;

class PurgeExpiredMessagesTest extends TestCase
{
    public function test_it_deletes_only_messages_older_than_one_year(): void
    {
        $this->travelTo(now());
        $sender = User::factory()->create();
        $conversation = Conversation::factory()->create();
        $cutoff = now()->subYear();

        $expired = Message::create([
            'user_id' => $sender->id,
            'conversation_id' => $conversation->id,
            'text' => 'Expired',
            'estado' => Message::STATE_NOLEIDO,
        ]);
        $expired->forceFill([
            'created_at' => $cutoff->copy()->subSecond(),
            'updated_at' => $cutoff->copy()->subSecond(),
        ])->saveQuietly();
        DB::table('user_message_read')->insert([
            'user_id' => $sender->id,
            'message_id' => $expired->id,
            'read' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $boundary = Message::create([
            'user_id' => $sender->id,
            'conversation_id' => $conversation->id,
            'text' => 'At cutoff',
            'estado' => Message::STATE_NOLEIDO,
        ]);
        $boundary->forceFill([
            'created_at' => $cutoff,
            'updated_at' => $cutoff,
        ])->saveQuietly();
        DB::table('user_message_read')->insert([
            'user_id' => $sender->id,
            'message_id' => $boundary->id,
            'read' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->artisan('messages:purge-expired')->assertExitCode(0);

        $this->assertDatabaseMissing('messages', ['id' => $expired->id]);
        $this->assertDatabaseMissing('user_message_read', ['message_id' => $expired->id]);
        $this->assertDatabaseHas('messages', ['id' => $boundary->id]);
        $this->assertDatabaseHas('user_message_read', ['message_id' => $boundary->id]);
    }

    public function test_it_reports_expired_messages_without_deleting_them_in_dry_run(): void
    {
        $sender = User::factory()->create();
        $conversation = Conversation::factory()->create();
        $expired = Message::create([
            'user_id' => $sender->id,
            'conversation_id' => $conversation->id,
            'text' => 'Expired',
            'estado' => Message::STATE_NOLEIDO,
        ]);
        $expired->forceFill(['created_at' => now()->subYear()->subSecond()])->saveQuietly();

        $this->artisan('messages:purge-expired --dry-run')
            ->expectsOutputToContain('Eligible: 1; planned: 1; deleted: 0; remaining: 1')
            ->assertExitCode(0);

        $this->assertDatabaseHas('messages', ['id' => $expired->id]);
    }

    public function test_it_respects_the_per_run_limit(): void
    {
        $sender = User::factory()->create();
        $conversation = Conversation::factory()->create();
        $messages = collect(range(1, 3))->map(function ($index) use ($sender, $conversation) {
            $message = Message::create([
                'user_id' => $sender->id,
                'conversation_id' => $conversation->id,
                'text' => "Expired {$index}",
                'estado' => Message::STATE_NOLEIDO,
            ]);
            $message->forceFill(['created_at' => now()->subYear()->subSecond()])->saveQuietly();

            return $message;
        });

        $this->artisan('messages:purge-expired --limit=2')
            ->expectsOutputToContain('Eligible: 3; deleted: 2; remaining: 1')
            ->assertExitCode(0);

        $this->assertSame(1, DB::table('messages')->whereIn('id', $messages->pluck('id'))->count());
    }

    public function test_it_rejects_non_positive_limits(): void
    {
        $this->artisan('messages:purge-expired --limit=0')
            ->expectsOutputToContain('The --limit option must be a positive integer.')
            ->assertExitCode(1);
    }
}
