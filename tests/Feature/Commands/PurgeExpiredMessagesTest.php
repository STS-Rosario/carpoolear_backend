<?php

namespace Tests\Feature\Commands;

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

        $this->artisan('messages:purge-expired')->assertExitCode(0);

        $this->assertDatabaseMissing('messages', ['id' => $expired->id]);
        $this->assertDatabaseHas('messages', ['id' => $boundary->id]);
    }
}
