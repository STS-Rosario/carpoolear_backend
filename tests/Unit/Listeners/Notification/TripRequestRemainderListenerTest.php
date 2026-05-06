<?php

namespace Tests\Unit\Listeners\Notification;

use STS\Events\Trip\Alert\RequestRemainder;
use STS\Listeners\Notification\TripRequestRemainder;
use STS\Models\Trip;
use STS\Models\User;
use STS\Notifications\RequestRemainderNotification;
use STS\Services\Notifications\NotificationServices;
use Tests\TestCase;

class TripRequestRemainderListenerTest extends TestCase
{
    public function test_handle_skips_notification_when_trip_has_no_driver(): void
    {
        $trip = (object) ['user' => null];

        $this->mock(NotificationServices::class)->shouldNotReceive('send');

        (new TripRequestRemainder)->handle(new RequestRemainder($trip));
    }

    public function test_handle_notifies_trip_owner_on_all_channels(): void
    {
        $driver = User::factory()->create();
        $trip = Trip::factory()->create(['user_id' => $driver->id]);
        $trip = $trip->fresh();

        $this->mock(NotificationServices::class)
            ->shouldReceive('send')
            ->times(3)
            ->withArgs(function ($notification, $users, $channel) use ($trip, $driver) {
                if (! $notification instanceof RequestRemainderNotification) {
                    return false;
                }

                return $notification->getAttribute('trip')->is($trip)
                    && $users instanceof User
                    && $users->is($driver)
                    && is_string($channel);
            });

        (new TripRequestRemainder)->handle(new RequestRemainder($trip));
    }
}
