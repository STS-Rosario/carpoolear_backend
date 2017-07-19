<?php

use Mockery as m;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use STS\User;
use STS\Entities\Trip;
use STS\Entities\Passenger;

class RequestNotAnswerTest extends TestCase
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

    public function testThreeDays()
    { 
        $driver = factory(User::class)->create();
        $passengerA = factory(User::class)->create();        
        $trip = factory(Trip::class)->create(['user_id' => $driver->id, 'trip_date' => Carbon\Carbon::now()->addDays(10)->toDateTimeString()]);
        factory(Passenger::class)->create(['user_id' => $passengerA->id, 'trip_id' => $trip->id, 'created_at' => Carbon\Carbon::now()->subDays(3)->toDateTimeString() ]);
        

        $status = $this->artisan('trip:requestnotanswer'); 
    }

    public function testNotSend()
    { 
        $driver = factory(User::class)->create();
        $passengerA = factory(User::class)->create();        
        $trip = factory(Trip::class)->create(['user_id' => $driver->id, 'trip_date' => Carbon\Carbon::now()->addDays(10)->toDateTimeString()]);
        factory(Passenger::class)->create(['user_id' => $passengerA->id, 'trip_id' => $trip->id, 'created_at' => Carbon\Carbon::now()->subDays(2)->toDateTimeString() ]);
        

        $status = $this->artisan('trip:requestnotanswer'); 
    }
 
    

     
}
