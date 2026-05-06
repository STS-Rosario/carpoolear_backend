<?php

namespace Tests\Unit\Listeners\Notification;

use STS\Events\Passenger\Request as RequestEvent;
use STS\Listeners\Notification\PassengerRequest;
use STS\Models\Trip;
use STS\Models\User;
use STS\Notifications\RequestPassengerNotification;
use STS\Services\Notifications\NotificationServices;
use Tests\TestCase;

class PassengerRequestTest extends TestCase
{
    public function test_handle_creates_notification_sets_attributes_and_notifies_when_recipient_exists(): void
    {
        $trip = Trip::factory()->create();
        $from = User::factory()->create();
        $to = User::factory()->create();

        $this->mock(NotificationServices::class)
            ->shouldReceive('send')
            ->times(3)
            ->withArgs(function ($notification, $users, $channel) use ($trip, $from, $to) {
                return $notification instanceof RequestPassengerNotification
                    && $notification->getAttribute('trip')->is($trip)
                    && $notification->getAttribute('from')->is($from)
                    && $users instanceof User
                    && $users->is($to)
                    && is_string($channel);
            });

        $listener = new PassengerRequest;
        $listener->handle(new RequestEvent($trip, $from, $to));
    }

    public function test_handle_does_nothing_when_recipient_is_null(): void
    {
        $trip = Trip::factory()->create();
        $from = User::factory()->create();

        $this->mock(NotificationServices::class)->shouldNotReceive('send');

        $listener = new PassengerRequest;
        $listener->handle(new RequestEvent($trip, $from, null));
    }
}
