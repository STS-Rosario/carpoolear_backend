<?php

use Illuminate\Foundation\Testing\DatabaseTransactions;
use STS\Entities\Trip;
use STS\Entities\Passenger;
use STS\User;


class PassengersTest extends TestCase
{
    use DatabaseTransactions;

    protected $passengerManager;

    public function setUp()
    {
        parent::setUp();
        start_log_query();
        $this->passengerManager = \App::make('\STS\Contracts\Logic\IPassengersLogic');
        $this->passengerRepository = \App::make('\STS\Contracts\Repository\IPassengersRepository');
    }

    public function testNewRequest()
    {
        $driver = factory(User::class)->create();
        $passenger = factory(User::class)->create();
        $trip = factory(Trip::class)->create(['user_id' => $driver->id ]); 
        $result = $this->passengerManager->newRequest($trip->id, $passenger);
        $this->assertNotNull($result);
        $this->assertTrue( $trip->passengerPending->count() > 0);
    }

    public function testAcceptRequest()
    {
        $driver = factory(User::class)->create();
        $passenger = factory(User::class)->create();
        $trip = factory(Trip::class)->create(['user_id' => $driver->id ]); 

        $this->passengerRepository->newRequest($trip->id, $passenger);

        $result = $this->passengerManager->acceptRequest($trip->id, $passenger->id, $driver);
        $this->assertNotNull($result);
        $this->assertTrue( $trip->passengerAccepted->count() > 0);
    }

    public function testRejectRequest()
    {
        $driver = factory(User::class)->create();
        $passenger = factory(User::class)->create();
        $trip = factory(Trip::class)->create(['user_id' => $driver->id ]); 

        $this->passengerRepository->newRequest($trip->id, $passenger);

        $result = $this->passengerManager->rejectRequest($trip->id, $passenger->id, $driver);
        $this->assertNotNull($result);
    }

    public function testCancelRequest()
    {
        $driver = factory(User::class)->create();
        $passenger = factory(User::class)->create();
        $trip = factory(Trip::class)->create(['user_id' => $driver->id ]); 
        $this->passengerRepository->newRequest($trip->id, $passenger);
        
        $result = $this->passengerManager->cancelRequest($trip->id, $passenger->id, $passenger);
        $this->assertNotNull($result);
        $this->assertTrue( $trip->passengerPending->count() == 0);
    }

    public function testGetPassenger()
    {
        $driver = factory(User::class)->create();
        $passengerA = factory(User::class)->create();
        $passengerB = factory(User::class)->create();
        $trip = factory(Trip::class)->create(['user_id' => $driver->id ]); 

        factory(Passenger::class, 'aceptado')->create(['user_id' => $passengerA->id, 'trip_id' => $trip->id]);
        factory(Passenger::class, 'aceptado')->create(['user_id' => $passengerB->id, 'trip_id' => $trip->id]);

        $result = $this->passengerManager->getPassengers($trip->id, $driver, []);
        $this->assertNotNull($result);
        $this->assertTrue( $result->count() > 0);
    }
 
    public function testPenddingPassenger()
    {
        $driver = factory(User::class)->create();
        $passengerA = factory(User::class)->create();
        $passengerB = factory(User::class)->create();
        $trip = factory(Trip::class)->create(['user_id' => $driver->id ]); 

        factory(Passenger::class)->create(['user_id' => $passengerA->id, 'trip_id' => $trip->id]);
        factory(Passenger::class, 'aceptado')->create(['user_id' => $passengerB->id, 'trip_id' => $trip->id]);

        $result = $this->passengerManager->getPendingRequests($trip->id, $driver, []);
        $this->assertNotNull($result);
        $this->assertTrue( $result->count() > 0);

        $result = $this->passengerManager->getPendingRequests(null, $driver, []); 
        $this->assertTrue( $result->count() > 0);
    }

}

