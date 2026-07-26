<?php

namespace Tests\Unit\Notifications;

use Carbon\Carbon;
use STS\Models\Conversation;
use STS\Models\Trip;
use STS\Models\User;
use STS\Notifications\TripGroupChatCreatedNotification;
use Tests\TestCase;

class TripGroupChatCreatedNotificationTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_to_string_uses_destination_date_and_hour(): void
    {
        Carbon::setTestNow('2026-07-26 14:30:00');
        $trip = Trip::factory()->create([
            'to_town' => 'Mar del Plata',
            'trip_date' => Carbon::parse('2026-08-01 09:00:00'),
        ]);
        $conversation = Conversation::factory()->create([
            'type' => Conversation::TYPE_TRIP_CONVERSATION,
            'trip_id' => $trip->id,
        ]);

        $notification = new TripGroupChatCreatedNotification;
        $notification->setAttribute('trip', $trip);
        $notification->setAttribute('conversation', $conversation);

        $this->assertSame(
            __('notifications.group_chat_created.message', [
                'destination' => 'Mar del Plata',
                'day' => '01/08/2026',
                'hour' => '09:00',
            ]),
            $notification->toString()
        );
    }

    public function test_get_extras_points_to_conversation_chat(): void
    {
        $trip = Trip::factory()->create();
        $conversation = Conversation::factory()->create([
            'type' => Conversation::TYPE_TRIP_CONVERSATION,
            'trip_id' => $trip->id,
        ]);

        $notification = new TripGroupChatCreatedNotification;
        $notification->setAttribute('trip', $trip);
        $notification->setAttribute('conversation', $conversation);

        $this->assertSame([
            'type' => 'conversation',
            'conversation_id' => $conversation->id,
        ], $notification->getExtras());
    }

    public function test_to_push_includes_conversation_url(): void
    {
        $user = User::factory()->create();
        $trip = Trip::factory()->create(['to_town' => 'Córdoba']);
        $conversation = Conversation::factory()->create([
            'type' => Conversation::TYPE_TRIP_CONVERSATION,
            'trip_id' => $trip->id,
        ]);

        $notification = new TripGroupChatCreatedNotification;
        $notification->setAttribute('trip', $trip);
        $notification->setAttribute('conversation', $conversation);

        $push = $notification->toPush($user, null);

        $this->assertSame('/conversations/'.$conversation->id, $push['url']);
        $this->assertSame('conversation', $push['type']);
        $this->assertSame($conversation->id, $push['extras']['id']);
    }
}
