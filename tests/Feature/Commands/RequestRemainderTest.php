<?php

namespace Tests\Feature\Commands;

use Mockery as m;
use Tests\TestCase;

class RequestRemainderTest extends TestCase
{
    protected $carsLogic;

    protected function setUp(): void
    {
        parent::setUp();
    }

    protected function tearDown(): void
    {
        m::close();
        parent::tearDown();
    }

    protected function parseJson($response)
    {
        return json_decode($response->getContent());
    }

    public function test_last_week()
    {
        $driver = \STS\Models\User::factory()->create();
        $passengerA = \STS\Models\User::factory()->create();
        $trip = \STS\Models\Trip::factory()->create(['user_id' => $driver->id, 'trip_date' => \Carbon\Carbon::now()->addDays(4)->toDateTimeString()]);
        \STS\Models\Passenger::factory()->create(['user_id' => $passengerA->id, 'trip_id' => $trip->id]);

        $this->artisan('trip:request')->assertSuccessful();
    }

    public function test_seccond_week()
    {
        $driver = \STS\Models\User::factory()->create();
        $passengerA = \STS\Models\User::factory()->create();
        $trip = \STS\Models\Trip::factory()->create(['user_id' => $driver->id, 'trip_date' => \Carbon\Carbon::now()->addDays(8)->toDateTimeString()]);
        \STS\Models\Passenger::factory()->create(['user_id' => $passengerA->id, 'trip_id' => $trip->id]);

        $this->artisan('trip:request')->assertSuccessful();
    }

    public function test_seccond_week_not_send()
    {
        $driver = \STS\Models\User::factory()->create();
        $passengerA = \STS\Models\User::factory()->create();
        $trip = \STS\Models\Trip::factory()->create(['user_id' => $driver->id, 'trip_date' => \Carbon\Carbon::now()->addDays(9)->toDateTimeString()]);
        \STS\Models\Passenger::factory()->create(['user_id' => $passengerA->id, 'trip_id' => $trip->id]);

        $this->artisan('trip:request')->assertSuccessful();
    }

    public function test_far_away_trip()
    {
        $driver = \STS\Models\User::factory()->create();
        $passengerA = \STS\Models\User::factory()->create();
        $trip = \STS\Models\Trip::factory()->create(['user_id' => $driver->id, 'trip_date' => \Carbon\Carbon::now()->addDays(16)->toDateTimeString()]);
        \STS\Models\Passenger::factory()->create(['user_id' => $passengerA->id, 'trip_id' => $trip->id]);

        $this->artisan('trip:request')->assertSuccessful();
    }
}
