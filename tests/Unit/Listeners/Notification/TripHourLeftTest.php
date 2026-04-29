<?php

namespace Tests\Unit\Listeners\Notification;

use STS\Events\Trip\Alert\HourLeft as HourLeftEvent;
use STS\Listeners\Notification\TripHourLeft;
use STS\Models\Trip;
use STS\Models\User;
use STS\Notifications\HourLeftNotification;
use STS\Services\Notifications\NotificationServices;
use Tests\TestCase;

class TripHourLeftTest extends TestCase
{
    public function test_handle_creates_notification_sets_trip_and_notifies_when_recipient_exists(): void
    {
        $trip = Trip::factory()->create();
        $to = User::factory()->create();

        $this->mock(NotificationServices::class)
            ->shouldReceive('send')
            ->times(3)
            ->withArgs(function ($notification, $users, $channel) use ($trip, $to) {
                return $notification instanceof HourLeftNotification
                    && $notification->getAttribute('trip')->is($trip)
                    && $users instanceof User
                    && $users->is($to)
                    && is_string($channel);
            });

        $listener = new TripHourLeft;
        $listener->handle(new HourLeftEvent($trip, $to));
    }

    public function test_handle_does_nothing_when_recipient_is_null(): void
    {
        $trip = Trip::factory()->create();

        $this->mock(NotificationServices::class)->shouldNotReceive('send');

        $listener = new TripHourLeft;
        $listener->handle(new HourLeftEvent($trip, null));
    }
}
