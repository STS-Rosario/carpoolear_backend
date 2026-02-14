<?php

namespace Tests;

use Tests\TestCase;
use STS\Models\User;
use STS\Models\Trip;
use STS\Models\Passenger;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class PassengersTest extends TestCase
{
    use DatabaseTransactions;

    protected $passengerManager;

    public function setUp(): void
    {
        parent::setUp();
        start_log_query();
        $this->passengerManager = \App::make(\STS\Services\Logic\PassengersManager::class);
        $this->passengerRepository = \App::make(\STS\Repository\PassengersRepository::class);
    }

    public function testNewRequest()
    {
        $driver = \STS\Models\User::factory()->create();
        $passenger = \STS\Models\User::factory()->create();
        $trip = \STS\Models\Trip::factory()->create(['user_id' => $driver->id]);
        $result = $this->passengerManager->newRequest($trip->id, $passenger);
        $this->assertNotNull($result);
        $this->assertTrue($trip->passengerPending->count() > 0);
    }

    public function testAcceptRequest()
    {
        $driver = \STS\Models\User::factory()->create();
        $passenger = \STS\Models\User::factory()->create();
        $trip = \STS\Models\Trip::factory()->create(['user_id' => $driver->id]);

        $this->passengerRepository->newRequest($trip->id, $passenger);

        $result = $this->passengerManager->acceptRequest($trip->id, $passenger->id, $driver);
        $this->assertNotNull($result);
        $this->assertTrue($trip->passengerAccepted->count() > 0);
    }

    public function testRejectRequest()
    {
        $driver = \STS\Models\User::factory()->create();
        $passenger = \STS\Models\User::factory()->create();
        $trip = \STS\Models\Trip::factory()->create(['user_id' => $driver->id]);

        $this->passengerRepository->newRequest($trip->id, $passenger);

        $result = $this->passengerManager->rejectRequest($trip->id, $passenger->id, $driver);
        $this->assertNotNull($result);
    }

    public function testCancelRequest()
    {
        $driver = \STS\Models\User::factory()->create();
        $passenger = \STS\Models\User::factory()->create();
        $trip = \STS\Models\Trip::factory()->create(['user_id' => $driver->id]);
        $this->passengerRepository->newRequest($trip->id, $passenger);

        $result = $this->passengerManager->cancelRequest($trip->id, $passenger->id, $passenger);
        $this->assertNotNull($result);
        $this->assertTrue($trip->passengerPending->count() == 0);
    }

    public function testGetPassenger()
    {
        $driver = \STS\Models\User::factory()->create();
        $passengerA = \STS\Models\User::factory()->create();
        $passengerB = \STS\Models\User::factory()->create();
        $trip = \STS\Models\Trip::factory()->create(['user_id' => $driver->id]);

        \STS\Models\Passenger::factory()->aceptado()->create(['user_id' => $passengerA->id, 'trip_id' => $trip->id]);
        \STS\Models\Passenger::factory()->aceptado()->create(['user_id' => $passengerB->id, 'trip_id' => $trip->id]);

        $result = $this->passengerManager->getPassengers($trip->id, $driver, []);
        $this->assertNotNull($result);
        $this->assertTrue($result->count() > 0);
    }

    public function testPenddingPassenger()
    {
        $driver = \STS\Models\User::factory()->create();
        $passengerA = \STS\Models\User::factory()->create();
        $passengerB = \STS\Models\User::factory()->create();
        $trip = \STS\Models\Trip::factory()->create(['user_id' => $driver->id]);

        \STS\Models\Passenger::factory()->create(['user_id' => $passengerA->id, 'trip_id' => $trip->id]);
        \STS\Models\Passenger::factory()->aceptado()->create(['user_id' => $passengerB->id, 'trip_id' => $trip->id]);

        $result = $this->passengerManager->getPendingRequests($trip->id, $driver, []);
        $this->assertNotNull($result);
        $this->assertTrue($result->count() > 0);

        $result = $this->passengerManager->getPendingRequests(null, $driver, []);
        $this->assertTrue($result->count() > 0);
    }
}
