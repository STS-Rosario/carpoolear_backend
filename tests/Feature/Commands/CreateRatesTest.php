<?php

namespace Tests\Feature\Commands;

use Tests\TestCase;
use STS\Models\User;
use STS\Models\Trip;
use STS\Models\Passenger;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class CreateRatesTest extends TestCase
{
    use DatabaseTransactions;

    public function testRunsSuccessfully()
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

    public function testRunsWithNoTrips()
    {
        $this->artisan('rate:create')->assertSuccessful();
    }
}
