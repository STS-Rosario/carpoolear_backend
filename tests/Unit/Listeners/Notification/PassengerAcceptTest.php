<?php

namespace Tests\Unit\Listeners\Notification;

use STS\Events\Passenger\Accept as AcceptEvent;
use STS\Listeners\Notification\PassengerAccept;
use STS\Models\Trip;
use STS\Models\User;
use STS\Notifications\AcceptPassengerNotification;
use STS\Services\Notifications\NotificationServices;
use Tests\TestCase;

class PassengerAcceptTest extends TestCase
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
                return $notification instanceof AcceptPassengerNotification
                    && $notification->getAttribute('trip')->is($trip)
                    && $notification->getAttribute('from')->is($from)
                    && $notification->getAttribute('token')->is($to)
                    && $users instanceof User
                    && $users->is($to)
                    && is_string($channel);
            });

        $listener = new PassengerAccept;
        $listener->handle(new AcceptEvent($trip, $from, $to));
    }

    public function test_handle_does_nothing_when_recipient_is_null(): void
    {
        $trip = Trip::factory()->create();
        $from = User::factory()->create();

        $this->mock(NotificationServices::class)->shouldNotReceive('send');

        $listener = new PassengerAccept;
        $listener->handle(new AcceptEvent($trip, $from, null));
    }
}
