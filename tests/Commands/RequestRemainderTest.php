<?php

namespace Tests\Commands;

use Tests\TestCase;
use STS\Models\User;
use Mockery as m;
use STS\Models\Trip;
use STS\Models\Passenger;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class RequestRemainderTest extends TestCase
{
    use DatabaseTransactions;

    protected $carsLogic;

    public function setUp(): void
    {
        parent::setUp();
    }

    public function tearDown(): void
    {
        m::close();
    }

    protected function parseJson($response)
    {
        return json_decode($response->getContent());
    }

    public function testLastWeek()
    {
        $driver = \STS\Models\User::factory()->create();
        $passengerA = \STS\Models\User::factory()->create();
        $trip = \STS\Models\Trip::factory()->create(['user_id' => $driver->id, 'trip_date' => \Carbon\Carbon::now()->addDays(4)->toDateTimeString()]);
        \STS\Models\Passenger::factory()->create(['user_id' => $passengerA->id, 'trip_id' => $trip->id]);

        $status = $this->artisan('trip:request');
    }

    public function testSeccondWeek()
    {
        $driver = \STS\Models\User::factory()->create();
        $passengerA = \STS\Models\User::factory()->create();
        $trip = \STS\Models\Trip::factory()->create(['user_id' => $driver->id, 'trip_date' => \Carbon\Carbon::now()->addDays(8)->toDateTimeString()]);
        \STS\Models\Passenger::factory()->create(['user_id' => $passengerA->id, 'trip_id' => $trip->id]);

        $status = $this->artisan('trip:request');
    }

    public function testSeccondWeekNotSend()
    {
        $driver = \STS\Models\User::factory()->create();
        $passengerA = \STS\Models\User::factory()->create();
        $trip = \STS\Models\Trip::factory()->create(['user_id' => $driver->id, 'trip_date' => \Carbon\Carbon::now()->addDays(9)->toDateTimeString()]);
        \STS\Models\Passenger::factory()->create(['user_id' => $passengerA->id, 'trip_id' => $trip->id]);

        $status = $this->artisan('trip:request');
    }

    public function testFarAwayTrip()
    {
        $driver = \STS\Models\User::factory()->create();
        $passengerA = \STS\Models\User::factory()->create();
        $trip = \STS\Models\Trip::factory()->create(['user_id' => $driver->id, 'trip_date' => \Carbon\Carbon::now()->addDays(16)->toDateTimeString()]);
        \STS\Models\Passenger::factory()->create(['user_id' => $passengerA->id, 'trip_id' => $trip->id]);

        $status = $this->artisan('trip:request');
    }
}
