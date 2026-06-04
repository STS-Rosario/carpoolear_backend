<?php

namespace Tests\Unit\Services;

use Carbon\Carbon;
use STS\Models\Passenger;
use STS\Models\Rating;
use STS\Models\Trip;
use STS\Models\User;
use STS\Services\AdminUserProfileCounts;
use Tests\TestCase;

class AdminUserProfileCountsTest extends TestCase
{
    public function test_ratings_count_sums_received_and_given_ratings(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $trip = Trip::factory()->create(['user_id' => $other->id]);

        Rating::factory()->create([
            'trip_id' => $trip->id,
            'user_id_to' => $user->id,
            'user_id_from' => $other->id,
        ]);
        Rating::factory()->create([
            'trip_id' => $trip->id,
            'user_id_from' => $user->id,
            'user_id_to' => $other->id,
        ]);

        $service = app(AdminUserProfileCounts::class);

        $this->assertSame(2, $service->ratingsCount($user->id));
    }

    public function test_trips_count_sums_active_and_old_driver_and_passenger_trips(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $driver = User::factory()->create();
        $passenger = User::factory()->create();
        $otherDriver = User::factory()->create();

        Trip::factory()->create([
            'user_id' => $driver->id,
            'trip_date' => Carbon::now()->addDay(),
            'weekly_schedule' => 0,
        ]);
        Trip::factory()->create([
            'user_id' => $driver->id,
            'trip_date' => Carbon::now()->subDays(10),
            'weekly_schedule' => 0,
        ]);

        $passengerTrip = Trip::factory()->create([
            'user_id' => $otherDriver->id,
            'trip_date' => Carbon::now()->addDay(),
            'weekly_schedule' => 0,
        ]);
        Passenger::factory()->aceptado()->create([
            'trip_id' => $passengerTrip->id,
            'user_id' => $passenger->id,
        ]);

        $oldPassengerTrip = Trip::factory()->create([
            'user_id' => $otherDriver->id,
            'trip_date' => Carbon::now()->subDays(10),
            'weekly_schedule' => 0,
        ]);
        Passenger::factory()->aceptado()->create([
            'trip_id' => $oldPassengerTrip->id,
            'user_id' => $passenger->id,
        ]);

        $service = app(AdminUserProfileCounts::class);

        $this->assertSame(2, $service->tripsCount($admin, $driver));
        $this->assertSame(2, $service->tripsCount($admin, $passenger));
    }
}
