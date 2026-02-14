<?php

namespace Tests;

use Mockery as m;
use Tests\TestCase;
use Carbon\Carbon;
use STS\Models\Passenger;
use STS\Models\TripPoint;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class TripsTest extends TestCase
{
    use DatabaseTransactions;

    public function setUp(): void
    {
        parent::setUp();
        start_log_query();
    }

    public function testCreateTrip()
    {
        \Illuminate\Support\Facades\Event::fake();
        $user = \STS\Models\User::factory()->create();
        $car = \STS\Models\Car::factory()->create(['user_id' => $user->id]);
        $tripManager = \App::make(\STS\Services\Logic\TripsManager::class);

        $data = [
            'is_passenger'          => 0,
            'from_town'             => 'Rosario, Santa Fe, Argentina',
            'to_town'               => 'Santa Fe, Santa Fe, Argentina',
            'trip_date'             => Carbon::now()->addDay(),
            'total_seats'           => 5,
            'friendship_type_id'    => 0,
            'estimated_time'        => '05:00',
            'distance'              => 365,
            'co2'                   => 50.0,
            'description'           => 'hola mundo',
            'car_id'                => $car->id,
            'points'                => [
                [
                    'address'      => 'Rosario, Santa Fe, Argentina',
                    'json_address' => ['street' => 'Pampa'],
                    'lat'          => 0,
                    'lng'          => 0,
                ], [
                    'address'      => 'Santa Fe, Santa Fe, Argentina',
                    'json_address' => ['street' => 'Pampa'],
                    'lat'          => 0,
                    'lng'          => 0,
                ],
            ],
            'enc_path' => 'sgwpEjbkaP_AvQjQlApD{l@',
        ];

        $u = $tripManager->create($user, $data);
        $this->assertTrue($u != null);
        \Illuminate\Support\Facades\Event::assertDispatched(\STS\Events\Trip\Create::class);
    }

    public function testUpdateTrip()
    {
        \Illuminate\Support\Facades\Event::fake();
        $tripManager = \App::make(\STS\Services\Logic\TripsManager::class);
        $trip = \STS\Models\Trip::factory()->create();
        $from = $trip->from_town;

        $data = [
            'from_town' => 'Usuahia',
            'enc_path'  => 'sgwpEjbkaP_AvQjQlApD{l@',
        ];

        $trip = $tripManager->update($trip->user, $trip->id, $data);
        $this->assertTrue($trip->from_town != $from);
        \Illuminate\Support\Facades\Event::assertDispatched(\STS\Events\Trip\Update::class);
    }

    public function testDeleteTrip()
    {
        \Illuminate\Support\Facades\Event::fake();
        $tripManager = \App::make(\STS\Services\Logic\TripsManager::class);
        $trip = \STS\Models\Trip::factory()->create();

        $from = $trip->from_town;
        $result = $tripManager->delete($trip->user, $trip->id);
        $this->assertTrue($result);
        \Illuminate\Support\Facades\Event::assertDispatched(\STS\Events\Trip\Delete::class);
    }

    public function testShowTrip()
    {
        $tripManager = \App::make(\STS\Services\Logic\TripsManager::class);
        $trip = \STS\Models\Trip::factory()->create();

        $result = $tripManager->show($trip->user, $trip->id);
        $this->assertTrue($result != null);
    }

    public function testCanSeeTrip()
    {
        $tripManager = \App::make(\STS\Services\Logic\TripsManager::class);
        $trip = \STS\Models\Trip::factory()->create();

        $other = \STS\Models\User::factory()->create();

        $result = $tripManager->userCanSeeTrip($other, $trip);
        $this->assertTrue($result);
    }

    public function testCanSeeTripFriend()
    {
        $friendsManager = \App::make(\STS\Services\Logic\FriendsManager::class);
        $tripManager = \App::make(\STS\Services\Logic\TripsManager::class);

        $trip = \STS\Models\Trip::factory()->create(['friendship_type_id' => 0]);
        $other = \STS\Models\User::factory()->create();

        // Make the users actual friends so the friendship check passes
        $friendsManager->make($other, $trip->user);

        $result = $tripManager->userCanSeeTrip($other, $trip);
        $this->assertTrue($result);
    }

    public function testTripSeeder()
    {
        $this->seed('TripsTestSeeder');

        $todos = TripPoint::all();
        $this->assertTrue($todos->count() == 2);
    }

    public function testSimpleSearch()
    {
        $tripManager = \App::make(\STS\Services\Logic\TripsManager::class);

        $this->seed('TripsTestSeeder');
        $other = \STS\Models\User::factory()->create();
        $data = [
            'date' => Carbon::now()->toDateString(),
        ];
        $trips = $tripManager->search($other, $data);
        $this->assertTrue($trips->count() > 0);
    }

    public function testOriginSearch()
    {
        $tripManager = \App::make(\STS\Services\Logic\TripsManager::class);

        $this->seed('TripsTestSeeder');
        $other = \STS\Models\User::factory()->create();
        $data = [
            'origin_lat'   => -32.946500,
            'origin_lng'   => -60.669800,
            'origin_radio' => 10000,
            'date'         => Carbon::now()->toDateString(),
        ];
        $trips = $tripManager->search($other, $data);
        $this->assertTrue($trips->count() > 0);
    }

    public function testDestinationSearch()
    {
        $tripManager = \App::make(\STS\Services\Logic\TripsManager::class);

        $this->seed('TripsTestSeeder');
        $other = \STS\Models\User::factory()->create();
        $data = [
            'destination_lat'   => -32.897273,
            'destination_lng'   => -68.834067,
            'destination_radio' => 10000,
        ];
        $trips = $tripManager->search($other, $data);
        $this->assertTrue($trips->count() > 0);
    }

    public function testInbounds()
    {
        $in = \STS\Models\Trip::factory()->create();
        $out = \STS\Models\Trip::factory()->create();

        $out->return_trip_id = $in->id;
        $out->save();

        $this->assertTrue($in->outbound != null);
        $this->assertTrue($out->inbound != null);
    }

    public function testMyTripsAsDriver()
    {
        $tripManager = \App::make(\STS\Services\Logic\TripsManager::class);
        $user = \STS\Models\User::factory()->create();
        $trip = \STS\Models\Trip::factory()->create(['user_id' => $user->id]);
        $trip = \STS\Models\Trip::factory()->create(['user_id' => $user->id]);

        $trips = $tripManager->getTrips($user, $user->id, true);

        $this->assertTrue($trips->count() > 0);
    }

    public function testMyTripsAsPassenger()
    {
        $tripManager = \App::make(\STS\Services\Logic\TripsManager::class);
        $user = \STS\Models\User::factory()->create();
        $trip = \STS\Models\Trip::factory()->create();
        \STS\Models\Passenger::factory()->aceptado()->create(['user_id' => $user->id, 'trip_id' => $trip->id]);

        $trips = $tripManager->getTrips($user, $user->id, false);

        $this->assertTrue($trips->count() > 0);
    }

    public function testUpdateListeners()
    {
        $driver = \STS\Models\User::factory()->create();
        $passengerA = \STS\Models\User::factory()->create();
        $passengerB = \STS\Models\User::factory()->create();
        $trip = \STS\Models\Trip::factory()->create(['user_id' => $driver->id]);

        \STS\Models\Passenger::factory()->aceptado()->create(['user_id' => $passengerA->id, 'trip_id' => $trip->id]);
        \STS\Models\Passenger::factory()->aceptado()->create(['user_id' => $passengerB->id, 'trip_id' => $trip->id]);

        $event = new \STS\Events\Trip\Update($trip);

        $listener = new \STS\Listeners\Notification\UpdateTrip();

        $listener->handle($event);

        $this->assertNotNull(\STS\Services\Notifications\Models\DatabaseNotification::all()->count() == 2);
    }
}
