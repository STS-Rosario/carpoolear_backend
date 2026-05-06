<?php

namespace Tests\Feature\Commands;

use Carbon\Carbon;
use STS\Models\Passenger;
use STS\Models\Trip;
use STS\Models\User;
use Tests\TestCase;

class CreateRatesTest extends TestCase
{
    public function test_runs_successfully()
    {
        $driver = User::factory()->create();
        $passenger = User::factory()->create();

        // Trip that ended yesterday (should be picked up by rate:create)
        $trip = Trip::factory()->create([
            'user_id' => $driver->id,
            'trip_date' => Carbon::now()->subHours(20),
        ]);

        Passenger::factory()->aceptado()->create([
            'user_id' => $passenger->id,
            'trip_id' => $trip->id,
        ]);

        $this->artisan('rate:create')->assertSuccessful();
    }

    public function test_runs_with_no_trips()
    {
        $this->artisan('rate:create')->assertSuccessful();
    }
}
