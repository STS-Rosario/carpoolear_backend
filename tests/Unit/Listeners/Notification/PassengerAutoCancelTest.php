<?php

namespace Tests\Unit\Listeners\Notification;

use STS\Events\Passenger\AutoCancel as AutoCancelEvent;
use STS\Listeners\Notification\PassengerAutoCancel;
use STS\Models\Trip;
use STS\Models\User;
use STS\Notifications\AutoCancelPassengerRequestIfRequestLimitedNotification;
use STS\Notifications\AutoCancelRequestIfRequestLimitedNotification;
use STS\Services\Notifications\NotificationServices;
use Tests\TestCase;

class PassengerAutoCancelTest extends TestCase
{
    public function test_handle_notifies_owner_and_passenger_when_both_participants_exist(): void
    {
        $trip = Trip::factory()->create();
        $from = User::factory()->create();
        $to = User::factory()->create();

        $index = 0;
        $this->mock(NotificationServices::class)
            ->shouldReceive('send')
            ->times(6)
            ->withArgs(function ($notification, $users, $channel) use (&$index, $trip, $from, $to) {
                $index++;
                if ($index <= 3) {
                    return $notification instanceof AutoCancelPassengerRequestIfRequestLimitedNotification
                        && $notification->getAttribute('trip')->is($trip)
                        && $notification->getAttribute('from')->is($to)
                        && $users instanceof User
                        && $users->is($from)
                        && is_string($channel);
                }

                return $notification instanceof AutoCancelRequestIfRequestLimitedNotification
                    && $notification->getAttribute('trip')->is($trip)
                    && $notification->getAttribute('from')->is($from)
                    && $users instanceof User
                    && $users->is($to)
                    && is_string($channel);
            });

        $listener = new PassengerAutoCancel;
        $listener->handle(new AutoCancelEvent($trip, $from, $to));
    }

    public function test_handle_skips_notifications_when_from_and_to_are_null(): void
    {
        $trip = Trip::factory()->create();

        $this->mock(NotificationServices::class)->shouldNotReceive('send');

        $listener = new PassengerAutoCancel;
        $listener->handle(new AutoCancelEvent($trip, null, null));
    }
}
