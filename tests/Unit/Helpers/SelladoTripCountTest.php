<?php

namespace Tests\Unit\Helpers;

use STS\Helpers\SelladoTripCount;
use STS\Models\Passenger;
use STS\Models\Trip;
use STS\Models\User;
use Tests\TestCase;

class SelladoTripCountTest extends TestCase
{
    public function test_for_user_can_exclude_the_trip_being_created_or_updated(): void
    {
        $user = User::factory()->create();
        $prior = Trip::factory()->create(['user_id' => $user->id]);
        $current = Trip::factory()->create(['user_id' => $user->id]);

        $this->assertSame(2, SelladoTripCount::forUser($user->id));
        $this->assertSame(1, SelladoTripCount::forUser($user->id, $current->id));
        $this->assertSame(1, SelladoTripCount::forUser($user->id, $prior->id));
    }

    public function test_passenger_trips_do_not_consume_the_free_trip_quota(): void
    {
        $user = User::factory()->create();

        Trip::factory()->count(3)->create([
            'user_id' => $user->id,
            'is_passenger' => true,
        ]);

        $this->assertSame(0, SelladoTripCount::forUser((int) $user->id));
    }

    public function test_driver_trips_still_consume_the_free_trip_quota(): void
    {
        $user = User::factory()->create();

        Trip::factory()->count(2)->create([
            'user_id' => $user->id,
            'is_passenger' => false,
        ]);

        $this->assertSame(2, SelladoTripCount::forUser((int) $user->id));
    }

    public function test_canceled_passenger_seeking_trips_do_not_count(): void
    {
        $user = User::factory()->create();

        $canceledPassengerSeeking = Trip::factory()->create([
            'user_id' => $user->id,
            'is_passenger' => true,
        ]);
        $canceledPassengerSeeking->delete();

        $this->assertSame(0, SelladoTripCount::forUser((int) $user->id));
    }

    public function test_canceled_driver_trips_with_accepted_passengers_still_count(): void
    {
        $user = User::factory()->create();
        $rider = User::factory()->create();

        $canceledWithPassenger = Trip::factory()->create([
            'user_id' => $user->id,
            'is_passenger' => false,
        ]);
        Passenger::factory()->aceptado()->create([
            'trip_id' => $canceledWithPassenger->id,
            'user_id' => $rider->id,
        ]);
        $canceledWithPassenger->delete();

        $this->assertSame(1, SelladoTripCount::forUser((int) $user->id));
    }
}
