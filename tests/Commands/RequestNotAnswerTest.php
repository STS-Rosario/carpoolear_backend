<?php

namespace Tests\Commands;

use Tests\TestCase;
use STS\Models\User;
use Mockery as m;
use STS\Models\Trip;
use STS\Models\Passenger;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class RequestNotAnswerTest extends TestCase
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

    public function testThreeDays()
    {
        $driver = \STS\Models\User::factory()->create();
        $passengerA = \STS\Models\User::factory()->create();
        $trip = \STS\Models\Trip::factory()->create(['user_id' => $driver->id, 'trip_date' => \Carbon\Carbon::now()->addDays(10)->toDateTimeString()]);
        \STS\Models\Passenger::factory()->create(['user_id' => $passengerA->id, 'trip_id' => $trip->id, 'created_at' => \Carbon\Carbon::now()->subDays(3)->toDateTimeString()]);

        $status = $this->artisan('trip:requestnotanswer');
    }

    public function testNotSend()
    {
        $driver = \STS\Models\User::factory()->create();
        $passengerA = \STS\Models\User::factory()->create();
        $trip = \STS\Models\Trip::factory()->create(['user_id' => $driver->id, 'trip_date' => \Carbon\Carbon::now()->addDays(10)->toDateTimeString()]);
        \STS\Models\Passenger::factory()->create(['user_id' => $passengerA->id, 'trip_id' => $trip->id, 'created_at' => \Carbon\Carbon::now()->subDays(2)->toDateTimeString()]);

        $status = $this->artisan('trip:requestnotanswer');
    }
}
