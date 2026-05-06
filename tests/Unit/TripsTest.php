<?php

namespace Tests\Unit;

use Carbon\Carbon;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use STS\Models\Passenger;
use STS\Models\Trip;
use STS\Models\TripPoint;
use STS\Models\User;
use STS\Services\Notifications\Models\DatabaseNotification;
use Tests\TestCase;

class TripsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    public function test_create_trip()
    {
        // TripsManager and TripRepository call OSRM for routing; CI often has no outbound HTTP.
        Http::fake(function ($request) {
            if (str_contains($request->url(), 'route/v1/driving')) {
                return Http::response([
                    'code' => 'Ok',
                    'routes' => [
                        [
                            'distance' => 365_000,
                            'duration' => 18_000,
                        ],
                    ],
                ], 200);
            }

            return Http::response('unexpected url in test', 404);
        });

        try {
            Event::fake();
            Carbon::setTestNow('2026-01-01 12:00:00');

            $user = User::factory()->create();
            $car = \STS\Models\Car::factory()->create(['user_id' => $user->id]);
            $tripManager = \App::make(\STS\Services\Logic\TripsManager::class);

            $data = [
                'is_passenger' => 0,
                'from_town' => 'Rosario, Santa Fe, Argentina',
                'to_town' => 'Santa Fe, Santa Fe, Argentina',
                'trip_date' => '2026-06-15 10:00:00',
                'total_seats' => 5,
                'friendship_type_id' => 0,
                'estimated_time' => '05:00',
                'distance' => 365,
                'co2' => 50.0,
                'description' => 'hola mundo',
                'car_id' => $car->id,
                'points' => [
                    [
                        'address' => 'Rosario, Santa Fe, Argentina',
                        'json_address' => ['street' => 'Pampa'],
                        'lat' => -32.9468,
                        'lng' => -60.6393,
                    ],
                    [
                        'address' => 'Santa Fe, Santa Fe, Argentina',
                        'json_address' => ['street' => 'Pampa'],
                        'lat' => -31.6333,
                        'lng' => -60.7000,
                    ],
                ],
                'enc_path' => 'sgwpEjbkaP_AvQjQlApD{l@',
            ];

            $u = $tripManager->create($user, $data);
            $this->assertNotNull($u);
            $this->assertSame($user->id, (int) $u->user_id);
            $this->assertSame(2, $u->points()->count());
            Event::assertDispatched(\STS\Events\Trip\Create::class);
        } finally {
            Carbon::setTestNow();
            Http::swap(new HttpFactory);
        }
    }

    public function test_update_trip()
    {
        \Illuminate\Support\Facades\Event::fake();
        $tripManager = \App::make(\STS\Services\Logic\TripsManager::class);
        $trip = \STS\Models\Trip::factory()->create();
        $from = $trip->from_town;

        $data = [
            'from_town' => 'Usuahia',
            'enc_path' => 'sgwpEjbkaP_AvQjQlApD{l@',
        ];

        $trip = $tripManager->update($trip->user, $trip->id, $data);
        $this->assertNotSame($from, $trip->from_town);
        $this->assertSame('Usuahia', $trip->from_town);
        \Illuminate\Support\Facades\Event::assertDispatched(\STS\Events\Trip\Update::class);
    }

    public function test_delete_trip()
    {
        \Illuminate\Support\Facades\Event::fake();
        $tripManager = \App::make(\STS\Services\Logic\TripsManager::class);
        $trip = \STS\Models\Trip::factory()->create();

        $result = $tripManager->delete($trip->user, $trip->id);
        $this->assertTrue($result);
        \Illuminate\Support\Facades\Event::assertDispatched(\STS\Events\Trip\Delete::class);
        $this->assertTrue((bool) $trip->fresh()->trashed());
    }

    public function test_show_trip()
    {
        $tripManager = \App::make(\STS\Services\Logic\TripsManager::class);
        $trip = \STS\Models\Trip::factory()->create();

        $result = $tripManager->show($trip->user, $trip->id);
        $this->assertNotNull($result);
        $this->assertTrue($result->is($trip));
    }

    public function test_can_see_trip()
    {
        $tripManager = \App::make(\STS\Services\Logic\TripsManager::class);
        $trip = \STS\Models\Trip::factory()->create();

        $other = User::factory()->create();

        $result = $tripManager->userCanSeeTrip($other, $trip);
        $this->assertTrue($result);
    }

    public function test_can_see_trip_friend()
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

    public function test_trip_seeder()
    {
        $this->seed('TripsTestSeeder');

        $todos = TripPoint::all();
        $this->assertCount(2, $todos);
    }

    public function test_simple_search()
    {
        $tripManager = \App::make(\STS\Services\Logic\TripsManager::class);

        $this->seed('TripsTestSeeder');
        $other = \STS\Models\User::factory()->create();
        $data = [
            'date' => Carbon::now()->toDateString(),
        ];
        $trips = $tripManager->search($other, $data);
        $this->assertGreaterThan(0, $trips->count());
    }

    public function test_origin_search()
    {
        $tripManager = \App::make(\STS\Services\Logic\TripsManager::class);

        $this->seed('TripsTestSeeder');
        $other = \STS\Models\User::factory()->create();
        $data = [
            'origin_lat' => -32.946500,
            'origin_lng' => -60.669800,
            'origin_radio' => 10000,
            'date' => Carbon::now()->toDateString(),
        ];
        $trips = $tripManager->search($other, $data);
        $this->assertGreaterThan(0, $trips->count());
    }

    public function test_destination_search()
    {
        $tripManager = \App::make(\STS\Services\Logic\TripsManager::class);

        $this->seed('TripsTestSeeder');
        $other = \STS\Models\User::factory()->create();
        $data = [
            'destination_lat' => -32.897273,
            'destination_lng' => -68.834067,
            'destination_radio' => 10000,
        ];
        $trips = $tripManager->search($other, $data);
        $this->assertGreaterThan(0, $trips->count());
    }

    public function test_inbounds()
    {
        $in = Trip::factory()->create();
        $out = Trip::factory()->create();

        $out->return_trip_id = $in->id;
        $out->save();

        $this->assertNotNull($in->fresh()->outbound);
        $this->assertNotNull($out->fresh()->inbound);
    }

    public function test_my_trips_as_driver()
    {
        $tripManager = \App::make(\STS\Services\Logic\TripsManager::class);
        $user = User::factory()->create();
        \STS\Models\Trip::factory()->create(['user_id' => $user->id]);
        \STS\Models\Trip::factory()->create(['user_id' => $user->id]);

        $trips = $tripManager->getTrips($user, $user->id, true);

        $this->assertCount(2, $trips);
    }

    public function test_my_trips_as_passenger()
    {
        $tripManager = \App::make(\STS\Services\Logic\TripsManager::class);
        $user = User::factory()->create();
        $trip = Trip::factory()->create();
        Passenger::factory()->aceptado()->create(['user_id' => $user->id, 'trip_id' => $trip->id]);

        $trips = $tripManager->getTrips($user, $user->id, false);

        $this->assertCount(1, $trips);
    }

    public function test_update_listeners()
    {
        $driver = \STS\Models\User::factory()->create();
        $passengerA = \STS\Models\User::factory()->create();
        $passengerB = \STS\Models\User::factory()->create();
        $trip = \STS\Models\Trip::factory()->create(['user_id' => $driver->id]);

        \STS\Models\Passenger::factory()->aceptado()->create(['user_id' => $passengerA->id, 'trip_id' => $trip->id]);
        \STS\Models\Passenger::factory()->aceptado()->create(['user_id' => $passengerB->id, 'trip_id' => $trip->id]);

        $event = new \STS\Events\Trip\Update($trip);

        $listener = new \STS\Listeners\Notification\UpdateTrip;

        $listener->handle($event);

        $this->assertSame(2, DatabaseNotification::query()->count());
    }
}
