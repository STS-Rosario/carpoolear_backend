<?php

namespace Tests\Commands;

use Tests\TestCase;
use STS\Models\User;
use Mockery as m;
use STS\Models\Trip;
use STS\Models\Passenger;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class HourLeftTest extends TestCase
{
    use DatabaseTransactions;

    protected $carsLogic;

    public function setUp(): void
    {
        parent::setUp();
        //$this->carsLogic = $this->mock(\STS\Services\Logic\CarsManager::class);
    }

    public function tearDown(): void
    {
        m::close();
    }

    protected function parseJson($response)
    {
        return json_decode($response->getContent());
    }

    public function testSomeMatch()
    {
        $driver = \STS\Models\User::factory()->create();
        $passengerA = \STS\Models\User::factory()->create();
        $passengerB = \STS\Models\User::factory()->create();
        $trip = \STS\Models\Trip::factory()->create(['user_id' => $driver->id, 'trip_date' => \Carbon\Carbon::now()->addHour()->toDateTimeString()]);

        \STS\Models\Passenger::factory()->aceptado()->create(['user_id' => $passengerA->id, 'trip_id' => $trip->id]);
        \STS\Models\Passenger::factory()->aceptado()->create(['user_id' => $passengerB->id, 'trip_id' => $trip->id]);

        $status = $this->artisan('trip:remainder');
    }

    public function testNoMatch()
    {
        $driver = \STS\Models\User::factory()->create();
        $passengerA = \STS\Models\User::factory()->create();
        $passengerB = \STS\Models\User::factory()->create();
        $trip = \STS\Models\Trip::factory()->create(['user_id' => $driver->id, 'trip_date' => \Carbon\Carbon::now()->addHours(2)->toDateTimeString()]);

        \STS\Models\Passenger::factory()->aceptado()->create(['user_id' => $passengerA->id, 'trip_id' => $trip->id]);
        \STS\Models\Passenger::factory()->aceptado()->create(['user_id' => $passengerB->id, 'trip_id' => $trip->id]);

        $status = $this->artisan('trip:remainder');
    }
}
