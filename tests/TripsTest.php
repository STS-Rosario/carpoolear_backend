<?php

use Mockery as m;
use Carbon\Carbon;
use STS\Entities\Passenger;
use STS\Entities\TripPoint;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class TripsTest extends TestCase
{
    use DatabaseTransactions;

    public function setUp()
    {
        parent::setUp();
        start_log_query();
    }

    public function testCreateTrip()
    {
        $this->expectsEvents(STS\Events\Trip\Create::class);
        $user = factory(STS\User::class)->create();
        $car = factory(STS\Entities\Car::class)->create(['user_id' => $user->id]);
        $tripManager = \App::make('\STS\Contracts\Logic\Trip');

        $data = [
            'is_passenger'          => 0,
            'from_town'             => 'Rosario, Santa Fe, Argentina',
            'to_town'               => 'Santa Fe, Santa Fe, Argentina',
            'trip_date'             => Carbon::now(),
            'total_seats'           => 5,
            'friendship_type_id'    => 0,
            'estimated_time'        => '05:00',
            'distance'              => 365,
            'co2'                   => 50,
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
    }

    public function testUpdateTrip()
    {
        $this->expectsEvents(STS\Events\Trip\Update::class);
        $tripManager = \App::make('\STS\Contracts\Logic\Trip');
        $trip = factory(STS\Entities\Trip::class)->create();
        $from = $trip->from_town;

        $data = [
            'from_town' => 'Usuahia',
            'enc_path'  => 'sgwpEjbkaP_AvQjQlApD{l@',
        ];

        $trip = $tripManager->update($trip->user, $trip->id, $data);
        $this->assertTrue($trip->from_town != $from);
    }

    public function testDeleteTrip()
    {
        $tripManager = \App::make('\STS\Contracts\Logic\Trip');
        $trip = factory(STS\Entities\Trip::class)->create();

        $from = $trip->from_town;
        $result = $tripManager->delete($trip->user, $trip->id);
        $this->assertTrue($result);
    }

    public function testShowTrip()
    {
        $tripManager = \App::make('\STS\Contracts\Logic\Trip');
        $trip = factory(STS\Entities\Trip::class)->create();

        $result = $tripManager->show($trip->user, $trip->id);
        $this->assertTrue($result != null);
    }

    public function testCanSeeTrip()
    {
        $tripManager = \App::make('\STS\Contracts\Logic\Trip');
        $trip = factory(STS\Entities\Trip::class)->create();

        $other = factory(STS\User::class)->create();

        $result = $tripManager->userCanSeeTrip($other, $trip);
        $this->assertTrue($result);
    }

    public function testCanSeeTripFriend()
    {
        $this->userLogic = $this->mock('STS\Contracts\Logic\Friends');
        $this->userLogic->shouldReceive('areFriend')->once()->andReturn(true);

        $tripManager = \App::make('\STS\Contracts\Logic\Trip');
        $trip = factory(STS\Entities\Trip::class)->create(['friendship_type_id' => 0]);

        $other = factory(STS\User::class)->create();

        $result = $tripManager->userCanSeeTrip($other, $trip);
        $this->assertTrue($result);

        m::close();
    }

    public function testTripSeeder()
    {
        $this->seed('TripsTestSeeder');

        $todos = TripPoint::all();
        $this->assertTrue($todos->count() == 2);
    }

    public function testSimpleSearch()
    {
        $tripManager = \App::make('\STS\Contracts\Logic\Trip');

        $this->seed('TripsTestSeeder');
        $other = factory(STS\User::class)->create();
        $data = [
            'date' => Carbon::now()->toDateString(),
        ];
        $trips = $tripManager->search($other, $data);
        $this->assertTrue($trips->count() > 0);
    }

    public function testOriginSearch()
    {
        $tripManager = \App::make('\STS\Contracts\Logic\Trip');

        $this->seed('TripsTestSeeder');
        $other = factory(STS\User::class)->create();
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
        $tripManager = \App::make('\STS\Contracts\Logic\Trip');

        $this->seed('TripsTestSeeder');
        $other = factory(STS\User::class)->create();
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
        $in = factory(STS\Entities\Trip::class)->create();
        $out = factory(STS\Entities\Trip::class)->create();

        $out->return_trip_id = $in->id;
        $out->save();

        $this->assertTrue($in->outbound != null);
        $this->assertTrue($out->inbound != null);
    }

    public function testMyTripsAsDriver()
    {
        $tripManager = \App::make('\STS\Contracts\Logic\Trip');
        $user = factory(STS\User::class)->create();
        $trip = factory(STS\Entities\Trip::class)->create(['user_id' => $user->id]);
        $trip = factory(STS\Entities\Trip::class)->create(['user_id' => $user->id]);

        $trips = $tripManager->myTrips($user, true);

        $this->assertTrue($trips->count() > 0);
    }

    public function testMyTripsAsPassenger()
    {
        $tripManager = \App::make('\STS\Contracts\Logic\Trip');
        $user = factory(STS\User::class)->create();
        $trip = factory(STS\Entities\Trip::class)->create();
        factory(Passenger::class, 'aceptado')->create(['user_id' => $user->id, 'trip_id' => $trip->id]);

        $trips = $tripManager->myTrips($user, false);

        $this->assertTrue($trips->count() > 0);
    }
}
