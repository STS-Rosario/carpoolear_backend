<?php

namespace Tests\Unit\Listeners\Notification;

use STS\Events\Passenger\Cancel as CancelEvent;
use STS\Listeners\Notification\PassengerCancel;
use STS\Models\Passenger;
use STS\Models\Trip;
use STS\Models\User;
use STS\Notifications\CancelPassengerNotification;
use STS\Services\Notifications\NotificationServices;
use Tests\TestCase;

class PassengerCancelTest extends TestCase
{
    public function test_handle_sets_driver_flag_true_when_canceled_by_driver_and_notifies(): void
    {
        $trip = Trip::factory()->create();
        $from = User::factory()->create();
        $to = User::factory()->create();

        $this->mock(NotificationServices::class)
            ->shouldReceive('send')
            ->times(3)
            ->withArgs(function ($notification, $users, $channel) use ($trip, $from, $to) {
                return $notification instanceof CancelPassengerNotification
                    && $notification->getAttribute('trip')->is($trip)
                    && $notification->getAttribute('from')->is($from)
                    && $notification->getAttribute('is_driver') === true
                    && $notification->getAttribute('canceledState') === Passenger::CANCELED_DRIVER
                    && $users instanceof User
                    && $users->is($to)
                    && is_string($channel);
            });

        $listener = new PassengerCancel;
        $listener->handle(new CancelEvent($trip, $from, $to, Passenger::CANCELED_DRIVER));
    }

    public function test_handle_sets_driver_flag_false_for_non_driver_state_and_skips_when_to_null(): void
    {
        $trip = Trip::factory()->create();
        $from = User::factory()->create();
        $to = User::factory()->create();

        $mock = $this->mock(NotificationServices::class);
        $mock->shouldReceive('send')
            ->times(3)
            ->withArgs(function ($notification, $users, $channel) use ($trip, $from, $to) {
                return $notification instanceof CancelPassengerNotification
                    && $notification->getAttribute('trip')->is($trip)
                    && $notification->getAttribute('from')->is($from)
                    && $notification->getAttribute('is_driver') === false
                    && $notification->getAttribute('canceledState') === Passenger::CANCELED_PASSENGER
                    && $users instanceof User
                    && $users->is($to)
                    && is_string($channel);
            });

        $listener = new PassengerCancel;
        $listener->handle(new CancelEvent($trip, $from, $to, Passenger::CANCELED_PASSENGER));

        $listener->handle(new CancelEvent($trip, $from, null, Passenger::CANCELED_PASSENGER));
    }
}
