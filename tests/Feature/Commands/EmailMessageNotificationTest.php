<?php

namespace Tests\Feature\Commands;

use Tests\TestCase;
use STS\Models\User;
use STS\Models\Conversation;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class EmailMessageNotificationTest extends TestCase
{
    use DatabaseTransactions;

    public function testMarksMessagesAsNotified()
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        // Create a conversation
        $conversation = Conversation::create([
            'trip_id' => null,
        ]);
        $conversation->users()->attach([$userA->id, $userB->id]);

        // Insert an unnotified message
        $messageId = DB::table('messages')->insertGetId([
            'user_id' => $userA->id,
            'conversation_id' => $conversation->id,
            'text' => 'Hello!',
            'already_notified' => 0,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);

        // Link message to recipient in read pivot
        DB::table('user_message_read')->insert([
            'message_id' => $messageId,
            'user_id' => $userB->id,
            'read' => 0,
        ]);

        $this->artisan('messages:email')->assertSuccessful();

        // Message should now be marked as notified
        $this->assertDatabaseHas('messages', [
            'id' => $messageId,
            'already_notified' => 1,
        ]);
    }

    public function testRunsSuccessfullyWithNoMessages()
    {
        $this->artisan('messages:email')->assertSuccessful();
    }
}
