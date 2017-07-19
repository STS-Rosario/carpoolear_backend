<?php

use Mockery as m;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use STS\User;
use STS\Entities\Trip;
use STS\Entities\Passenger;

class RequestRemainderTest extends TestCase
{
    use DatabaseTransactions;

    protected $carsLogic;

    public function __construct()
    {
    }

    public function setUp()
    {
        parent::setUp();
    }

    public function tearDown()
    {
        m::close();
    } 

    protected function parseJson($response)
    {
        return json_decode($response->getContent());
    }

    public function testLastWeek()
    { 
        $driver = factory(User::class)->create();
        $passengerA = factory(User::class)->create();        
        $trip = factory(Trip::class)->create(['user_id' => $driver->id, 'trip_date' => Carbon\Carbon::now()->addDays(4)->toDateTimeString()]);
        factory(Passenger::class)->create(['user_id' => $passengerA->id, 'trip_id' => $trip->id]);
        

        $status = $this->artisan('trip:request'); 
    }

    public function testSeccondWeek()
    { 
        $driver = factory(User::class)->create();
        $passengerA = factory(User::class)->create();        
        $trip = factory(Trip::class)->create(['user_id' => $driver->id, 'trip_date' => Carbon\Carbon::now()->addDays(8)->toDateTimeString()]);
        factory(Passenger::class)->create(['user_id' => $passengerA->id, 'trip_id' => $trip->id]);
        

        $status = $this->artisan('trip:request'); 
    }

    public function testSeccondWeekNotSend()
    { 
        $driver = factory(User::class)->create();
        $passengerA = factory(User::class)->create();        
        $trip = factory(Trip::class)->create(['user_id' => $driver->id, 'trip_date' => Carbon\Carbon::now()->addDays(9)->toDateTimeString()]);
        factory(Passenger::class)->create(['user_id' => $passengerA->id, 'trip_id' => $trip->id]);
        

        $status = $this->artisan('trip:request'); 
    }

    public function testFarAwayTrip()
    { 
        $driver = factory(User::class)->create();
        $passengerA = factory(User::class)->create();        
        $trip = factory(Trip::class)->create(['user_id' => $driver->id, 'trip_date' => Carbon\Carbon::now()->addDays(16)->toDateTimeString()]);
        factory(Passenger::class)->create(['user_id' => $passengerA->id, 'trip_id' => $trip->id]);
        

        $status = $this->artisan('trip:request'); 
    }

    

     
}
