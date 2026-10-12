<?php

namespace Tests\Unit\Listeners\Conversation;

use STS\Events\Trip\Delete as TripDeleted;
use STS\Listeners\Conversation\CloseGroupChatOnTripDelete;
use STS\Models\Conversation;
use STS\Models\Trip;
use STS\Models\User;
use Tests\TestCase;

class CloseGroupChatOnTripDeleteListenerTest extends TestCase
{
    public function test_handle_closes_open_trip_group_chat_when_trip_is_deleted(): void
    {
        $driver = User::factory()->create();
        $trip = Trip::factory()->create(['user_id' => $driver->id]);
        $conversation = Conversation::factory()->create([
            'trip_id' => $trip->id,
            'type' => Conversation::TYPE_TRIP_CONVERSATION,
        ]);
        $conversation->users()->attach($driver->id, ['read' => true]);

        $this->app->make(CloseGroupChatOnTripDelete::class)->handle(new TripDeleted($trip->fresh()));

        $this->assertNotNull($conversation->fresh()->closed_at);
    }

    public function test_handle_does_not_rewrite_closed_at_when_chat_is_already_closed(): void
    {
        $driver = User::factory()->create();
        $trip = Trip::factory()->create(['user_id' => $driver->id]);
        $conversation = Conversation::factory()->create([
            'trip_id' => $trip->id,
            'type' => Conversation::TYPE_TRIP_CONVERSATION,
            'closed_at' => now()->subDay(),
        ]);
        $conversation->users()->attach($driver->id, ['read' => true]);

        $this->app->make(CloseGroupChatOnTripDelete::class)->handle(new TripDeleted($trip->fresh()));

        $this->assertSame(
            $conversation->closed_at->toDateTimeString(),
            $conversation->fresh()->closed_at->toDateTimeString()
        );
    }

    public function test_handle_does_nothing_when_trip_has_no_group_chat(): void
    {
        $driver = User::factory()->create();
        $trip = Trip::factory()->create(['user_id' => $driver->id]);
        $private = Conversation::factory()->create([
            'type' => Conversation::TYPE_PRIVATE_CONVERSATION,
        ]);
        $private->users()->attach($driver->id, ['read' => true]);

        $this->app->make(CloseGroupChatOnTripDelete::class)->handle(new TripDeleted($trip->fresh()));

        $this->assertNull($private->fresh()->closed_at);
    }
}