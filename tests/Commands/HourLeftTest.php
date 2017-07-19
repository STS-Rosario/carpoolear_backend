<?php

use Mockery as m;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use STS\User;
use STS\Entities\Trip;
use STS\Entities\Passenger;

class HourLeftTest extends TestCase
{
    use DatabaseTransactions;

    protected $carsLogic;

    public function __construct()
    {
    }

    public function setUp()
    {
        parent::setUp();
        //$this->carsLogic = $this->mock('STS\Contracts\Logic\Car');
    }

    public function tearDown()
    {
        m::close();
    } 

    protected function parseJson($response)
    {
        return json_decode($response->getContent());
    }

    public function testSomeMatch()
    { 
        $driver = factory(User::class)->create();
        $passengerA = factory(User::class)->create();
        $passengerB = factory(User::class)->create();
        $trip = factory(Trip::class)->create(['user_id' => $driver->id, 'trip_date' => Carbon\Carbon::now()->addHour()->toDateTimeString()]);

        factory(Passenger::class, 'aceptado')->create(['user_id' => $passengerA->id, 'trip_id' => $trip->id]);
        factory(Passenger::class, 'aceptado')->create(['user_id' => $passengerB->id, 'trip_id' => $trip->id]);

        $status = $this->artisan('trip:remainder'); 
    }

    public function testNoMatch()
    { 
        $driver = factory(User::class)->create();
        $passengerA = factory(User::class)->create();
        $passengerB = factory(User::class)->create();
        $trip = factory(Trip::class)->create(['user_id' => $driver->id, 'trip_date' => Carbon\Carbon::now()->addHours(2)->toDateTimeString()]);

        factory(Passenger::class, 'aceptado')->create(['user_id' => $passengerA->id, 'trip_id' => $trip->id]);
        factory(Passenger::class, 'aceptado')->create(['user_id' => $passengerB->id, 'trip_id' => $trip->id]);

        $status = $this->artisan('trip:remainder'); 
    }

     
}
