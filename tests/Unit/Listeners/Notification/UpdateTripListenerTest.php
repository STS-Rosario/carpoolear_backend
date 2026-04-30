<?php

namespace Tests\Unit\Listeners\Notification;

use STS\Events\Trip\Update as UpdateEvent;
use STS\Listeners\Notification\UpdateTrip;
use STS\Models\Passenger;
use STS\Models\Trip;
use STS\Models\User;
use STS\Notifications\UpdateTripNotification;
use STS\Services\Notifications\NotificationServices;
use Tests\TestCase;

class UpdateTripListenerTest extends TestCase
{
    public function test_handle_does_not_send_when_trip_has_no_accepted_passengers(): void
    {
        $driver = User::factory()->create();
        $trip = Trip::factory()->create(['user_id' => $driver->id]);

        $this->mock(NotificationServices::class)->shouldNotReceive('send');

        (new UpdateTrip)->handle(new UpdateEvent($trip->fresh()));
    }

    public function test_handle_does_not_send_when_only_pending_passengers_exist(): void
    {
        $driver = User::factory()->create();
        $trip = Trip::factory()->create(['user_id' => $driver->id]);

        Passenger::factory()->create([
            'trip_id' => $trip->id,
            'user_id' => User::factory(),
            'request_state' => Passenger::STATE_PENDING,
        ]);

        $this->mock(NotificationServices::class)->shouldNotReceive('send');

        (new UpdateTrip)->handle(new UpdateEvent($trip->fresh()));
    }

    public function test_handle_notifies_single_accepted_passenger_on_all_channels(): void
    {
        $driver = User::factory()->create();
        $trip = Trip::factory()->create(['user_id' => $driver->id]);
        $passengerUser = User::factory()->create();

        Passenger::factory()->aceptado()->create([
            'trip_id' => $trip->id,
            'user_id' => $passengerUser->id,
        ]);

        $trip = $trip->fresh();

        $this->mock(NotificationServices::class)
            ->shouldReceive('send')
            ->times(3)
            ->withArgs(function ($notification, $users, $channel) use ($trip, $driver, $passengerUser) {
                if (! $notification instanceof UpdateTripNotification) {
                    return false;
                }

                return $notification->getAttribute('trip')->is($trip)
                    && $notification->getAttribute('from')->is($driver)
                    && $users instanceof User
                    && $users->is($passengerUser)
                    && is_string($channel);
            });

        (new UpdateTrip)->handle(new UpdateEvent($trip));
    }

    public function test_handle_notifies_each_accepted_passenger_on_all_channels(): void
    {
        $driver = User::factory()->create();
        $trip = Trip::factory()->create(['user_id' => $driver->id]);
        $first = User::factory()->create();
        $second = User::factory()->create();

        Passenger::factory()->aceptado()->create([
            'trip_id' => $trip->id,
            'user_id' => $first->id,
        ]);
        Passenger::factory()->aceptado()->create([
            'trip_id' => $trip->id,
            'user_id' => $second->id,
        ]);

        $trip = $trip->fresh();

        $this->mock(NotificationServices::class)
            ->shouldReceive('send')
            ->times(6)
            ->withArgs(function ($notification, $users, $channel) use ($trip, $driver, $first, $second) {
                if (! $notification instanceof UpdateTripNotification) {
                    return false;
                }

                if (! $notification->getAttribute('trip')->is($trip)
                    || ! $notification->getAttribute('from')->is($driver)) {
                    return false;
                }

                if (! $users instanceof User) {
                    return false;
                }

                return ($users->is($first) || $users->is($second)) && is_string($channel);
            });

        (new UpdateTrip)->handle(new UpdateEvent($trip));
    }
}
