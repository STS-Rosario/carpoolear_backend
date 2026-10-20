<?php

namespace Tests\Unit\Console\Commands;

use Carbon\Carbon;
use STS\Models\Conversation;
use STS\Models\Trip;
use STS\Models\User;
use Tests\TestCase;

class CloseFinishedTripGroupChatsTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function tripConversation(Trip $trip): Conversation
    {
        $conversation = Conversation::factory()->create([
            'trip_id' => $trip->id,
            'type' => Conversation::TYPE_TRIP_CONVERSATION,
        ]);
        $conversation->users()->attach($trip->user_id, ['read' => true]);

        return $conversation;
    }

    public function test_command_closes_group_chats_finished_more_than_48_hours_ago(): void
    {
        Carbon::setTestNow('2026-08-05 12:00:00');
        $user = User::factory()->create();

        $finished = Trip::factory()->create([
            'user_id' => $user->id,
            'trip_date' => Carbon::parse('2026-08-01 09:00:00'),
        ]);
        $conversation = $this->tripConversation($finished);

        $this->artisan('conversation:close-finished-group-chats')->assertExitCode(0);

        $this->assertNotNull($conversation->fresh()->closed_at);
    }

    public function test_command_does_not_close_chats_for_recent_or_future_trips(): void
    {
        Carbon::setTestNow('2026-08-05 12:00:00');
        $user = User::factory()->create();

        $recent = Trip::factory()->create([
            'user_id' => $user->id,
            'trip_date' => Carbon::parse('2026-08-05 09:00:00'),
        ]);
        $future = Trip::factory()->create([
            'user_id' => $user->id,
            'trip_date' => Carbon::parse('2026-08-20 09:00:00'),
        ]);
        $recentChat = $this->tripConversation($recent);
        $futureChat = $this->tripConversation($future);

        $this->artisan('conversation:close-finished-group-chats')->assertExitCode(0);

        $this->assertNull($recentChat->fresh()->closed_at);
        $this->assertNull($futureChat->fresh()->closed_at);
    }

    public function test_command_does_not_close_chats_for_weekly_schedule_trips(): void
    {
        Carbon::setTestNow('2026-08-05 12:00:00');
        $user = User::factory()->create();

        $weekly = Trip::factory()->create([
            'user_id' => $user->id,
            'trip_date' => null,
            'weekly_schedule' => 2,
        ]);
        $chat = $this->tripConversation($weekly);

        $this->artisan('conversation:close-finished-group-chats')->assertExitCode(0);

        $this->assertNull($chat->fresh()->closed_at);
    }

    public function test_command_leaves_already_closed_chats_untouched(): void
    {
        Carbon::setTestNow('2026-08-05 12:00:00');
        $user = User::factory()->create();

        $trip = Trip::factory()->create([
            'user_id' => $user->id,
            'trip_date' => Carbon::parse('2026-08-01 09:00:00'),
        ]);
        $alreadyClosed = Conversation::factory()->create([
            'trip_id' => $trip->id,
            'type' => Conversation::TYPE_TRIP_CONVERSATION,
            'closed_at' => now()->subDay(),
        ]);

        $this->artisan('conversation:close-finished-group-chats')->assertExitCode(0);

        $this->assertSame(
            $alreadyClosed->closed_at->toDateTimeString(),
            $alreadyClosed->fresh()->closed_at->toDateTimeString()
        );
    }

    public function test_command_closes_chats_of_soft_deleted_trips(): void
    {
        Carbon::setTestNow('2026-08-05 12:00:00');
        $user = User::factory()->create();

        $deletedTrip = Trip::factory()->create([
            'user_id' => $user->id,
            'trip_date' => Carbon::parse('2026-08-20 09:00:00'),
        ]);
        $deletedTrip->delete();
        $chat = $this->tripConversation($deletedTrip);

        $this->artisan('conversation:close-finished-group-chats')->assertExitCode(0);

        $this->assertNotNull($chat->fresh()->closed_at);
    }

    public function test_command_closes_chats_of_canceled_trips(): void
    {
        Carbon::setTestNow('2026-08-05 12:00:00');
        $user = User::factory()->create();

        $canceledTrip = Trip::factory()->create([
            'user_id' => $user->id,
            'trip_date' => Carbon::parse('2026-08-20 09:00:00'),
            'state' => Trip::STATE_CANCELED,
        ]);
        $chat = $this->tripConversation($canceledTrip);

        $this->artisan('conversation:close-finished-group-chats')->assertExitCode(0);

        $this->assertNotNull($chat->fresh()->closed_at);
    }
}
