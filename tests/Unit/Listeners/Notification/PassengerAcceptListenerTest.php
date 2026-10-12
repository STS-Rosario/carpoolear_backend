<?php

namespace Tests\Unit\Listeners\Notification;

use STS\Events\Passenger\Accept;
use STS\Listeners\Notification\PassengerAccept;
use STS\Models\Conversation;
use STS\Models\Trip;
use STS\Models\User;
use STS\Notifications\AcceptPassengerNotification;
use STS\Services\Notifications\NotificationServices;
use Tests\TestCase;

class PassengerAcceptListenerTest extends TestCase
{
    public function test_handle_attaches_group_chat_when_accepted_user_is_already_a_member(): void
    {
        $driver = User::factory()->create();
        $to = User::factory()->create();
        $trip = Trip::factory()->create(['user_id' => $driver->id]);
        $conversation = Conversation::factory()->create([
            'trip_id' => $trip->id,
            'type' => Conversation::TYPE_TRIP_CONVERSATION,
        ]);
        $conversation->users()->attach($driver->id, ['read' => true]);
        $conversation->users()->attach($to->id, ['read' => true]);

        $captured = [];
        $this->mock(NotificationServices::class)
            ->shouldReceive('send')
            ->times(3)
            ->withArgs(function ($notification) use (&$captured) {
                $captured[] = $notification;

                return true;
            });

        (new PassengerAccept)->handle(new Accept($trip->fresh(), $driver, $to));

        $sent = collect($captured)->first(fn ($n) => $n instanceof AcceptPassengerNotification);
        $this->assertNotNull($sent);
        $this->assertNotNull($sent->getAttribute('conversation'));
        $this->assertTrue($sent->getAttribute('conversation')->is($conversation));
        $this->assertSame('conversation', $sent->getExtras()['type']);
    }

    public function test_handle_leaves_conversation_unset_when_accepted_user_is_not_a_member(): void
    {
        $driver = User::factory()->create();
        $to = User::factory()->create();
        $trip = Trip::factory()->create(['user_id' => $driver->id]);
        $conversation = Conversation::factory()->create([
            'trip_id' => $trip->id,
            'type' => Conversation::TYPE_TRIP_CONVERSATION,
        ]);
        $conversation->users()->attach($driver->id, ['read' => true]);

        $captured = [];
        $this->mock(NotificationServices::class)
            ->shouldReceive('send')
            ->times(3)
            ->withArgs(function ($notification) use (&$captured) {
                $captured[] = $notification;

                return true;
            });

        (new PassengerAccept)->handle(new Accept($trip->fresh(), $driver, $to));

        $sent = collect($captured)->first(fn ($n) => $n instanceof AcceptPassengerNotification);
        $this->assertNotNull($sent);
        $this->assertNull($sent->getAttribute('conversation'));
        $this->assertSame('trip', $sent->getExtras()['type']);
    }
}