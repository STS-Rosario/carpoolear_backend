<?php

namespace Tests\Feature\Commands;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use STS\Models\Conversation;
use STS\Models\User;
use Tests\TestCase;

class EmailMessageNotificationTest extends TestCase
{
    public function test_marks_messages_as_notified()
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

    public function test_runs_successfully_with_no_messages()
    {
        $this->artisan('messages:email')->assertSuccessful();
    }
}
