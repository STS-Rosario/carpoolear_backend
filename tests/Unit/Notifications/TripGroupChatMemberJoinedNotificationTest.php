<?php

namespace Tests\Unit\Notifications;

use STS\Models\Conversation;
use STS\Models\Trip;
use STS\Models\User;
use STS\Notifications\TripGroupChatMemberJoinedNotification;
use STS\Services\Notifications\Channels\DatabaseChannel;
use STS\Services\Notifications\Channels\PushChannel;
use Tests\TestCase;

class TripGroupChatMemberJoinedNotificationTest extends TestCase
{
    public function test_via_contains_database_and_push_channels(): void
    {
        $notification = new TripGroupChatMemberJoinedNotification;

        $this->assertSame([
            DatabaseChannel::class,
            PushChannel::class,
        ], $notification->getVia());
    }

    public function test_to_string_uses_joined_user_name(): void
    {
        $joiner = User::factory()->create(['name' => 'Ana']);
        $trip = Trip::factory()->create();
        $conversation = Conversation::factory()->create([
            'type' => Conversation::TYPE_TRIP_CONVERSATION,
            'trip_id' => $trip->id,
        ]);

        $notification = new TripGroupChatMemberJoinedNotification;
        $notification->setAttribute('user', $joiner);
        $notification->setAttribute('trip', $trip);
        $notification->setAttribute('conversation', $conversation);

        $this->assertSame(
            __('notifications.group_chat_user_joined', ['name' => 'Ana']),
            $notification->toString()
        );
    }

    public function test_get_extras_points_to_conversation_chat(): void
    {
        $joiner = User::factory()->create();
        $trip = Trip::factory()->create();
        $conversation = Conversation::factory()->create([
            'type' => Conversation::TYPE_TRIP_CONVERSATION,
            'trip_id' => $trip->id,
        ]);

        $notification = new TripGroupChatMemberJoinedNotification;
        $notification->setAttribute('user', $joiner);
        $notification->setAttribute('conversation', $conversation);

        $this->assertSame([
            'type' => 'conversation',
            'conversation_id' => $conversation->id,
        ], $notification->getExtras());
    }

    public function test_to_push_includes_conversation_url(): void
    {
        $joiner = User::factory()->create(['name' => 'Ana']);
        $trip = Trip::factory()->create();
        $conversation = Conversation::factory()->create([
            'type' => Conversation::TYPE_TRIP_CONVERSATION,
            'trip_id' => $trip->id,
        ]);

        $notification = new TripGroupChatMemberJoinedNotification;
        $notification->setAttribute('user', $joiner);
        $notification->setAttribute('trip', $trip);
        $notification->setAttribute('conversation', $conversation);

        $push = $notification->toPush(null, null);

        $this->assertSame('/conversations/'.$conversation->id, $push['url']);
        $this->assertSame($conversation->id, $push['extras']['id']);
    }
}