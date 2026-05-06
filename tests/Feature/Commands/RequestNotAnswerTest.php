<?php

namespace Tests\Feature\Commands;

use Mockery as m;
use Tests\TestCase;

class RequestNotAnswerTest extends TestCase
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

    public function test_three_days()
    {
        $driver = \STS\Models\User::factory()->create();
        $passengerA = \STS\Models\User::factory()->create();
        $trip = \STS\Models\Trip::factory()->create(['user_id' => $driver->id, 'trip_date' => \Carbon\Carbon::now()->addDays(10)->toDateTimeString()]);
        \STS\Models\Passenger::factory()->create(['user_id' => $passengerA->id, 'trip_id' => $trip->id, 'created_at' => \Carbon\Carbon::now()->subDays(3)->toDateTimeString()]);

        $this->artisan('trip:requestnotanswer')->assertSuccessful();
    }

    public function test_not_send()
    {
        $driver = \STS\Models\User::factory()->create();
        $passengerA = \STS\Models\User::factory()->create();
        $trip = \STS\Models\Trip::factory()->create(['user_id' => $driver->id, 'trip_date' => \Carbon\Carbon::now()->addDays(10)->toDateTimeString()]);
        \STS\Models\Passenger::factory()->create(['user_id' => $passengerA->id, 'trip_id' => $trip->id, 'created_at' => \Carbon\Carbon::now()->subDays(2)->toDateTimeString()]);

        $this->artisan('trip:requestnotanswer')->assertSuccessful();
    }
}
