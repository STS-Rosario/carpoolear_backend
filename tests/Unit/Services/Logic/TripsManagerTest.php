<?php

namespace Tests\Unit\Services\Logic;

use Carbon\Carbon;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Mockery;
use STS\Events\Trip\Create as CreateEvent;
use STS\Events\Trip\Delete as DeleteEvent;
use STS\Models\Car;
use STS\Models\Passenger;
use STS\Models\Trip;
use STS\Models\TripPoint;
use STS\Models\User;
use STS\Repository\FriendsRepository;
use STS\Repository\TripRepository;
use STS\Services\Logic\FriendsManager;
use STS\Services\Logic\TripsManager;
use STS\Services\Logic\UsersManager;
use Tests\TestCase;

class TripsManagerTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function manager(): TripsManager
    {
        return $this->app->make(TripsManager::class);
    }

    /**
     * @return array<string, mixed>
     */
    private function minimalCreatePayload(array $overrides = []): array
    {
        return array_merge([
            'is_passenger' => 0,
            'from_town' => 'Origin Town',
            'to_town' => 'Destination Town',
            'trip_date' => '2028-03-10 15:00:00',
            'total_seats' => 3,
            'friendship_type_id' => Trip::PRIVACY_PUBLIC,
            'estimated_time' => '01:30',
            'distance' => 100,
            'co2' => 10,
            'description' => 'unit test trip',
            'points' => [
                [
                    'address' => 'Point A',
                    'json_address' => ['name' => 'A', 'state' => 'BA'],
                    'lat' => -34.6,
                    'lng' => -58.4,
                ],
                [
                    'address' => 'Point B',
                    'json_address' => ['name' => 'B', 'state' => 'BA'],
                    'lat' => -34.7,
                    'lng' => -58.5,
                ],
            ],
        ], $overrides);
    }

    public function test_validator_create_requires_core_fields(): void
    {
        Carbon::setTestNow('2028-01-01 12:00:00');
        $user = User::factory()->create();
        $v = $this->manager()->validator([], $user->id);
        $this->assertTrue($v->fails());
        $this->assertTrue($v->errors()->has('is_passenger'));
        $this->assertTrue($v->errors()->has('from_town'));
        Carbon::setTestNow();
    }

    public function test_validator_create_includes_all_documented_rule_keys(): void
    {
        Carbon::setTestNow('2028-01-01 12:00:00');
        $user = User::factory()->create();
        $rules = $this->manager()->validator($this->minimalCreatePayload(), $user->id)->getRules();
        $expected = [
            'is_passenger', 'from_town', 'to_town', 'trip_date', 'total_seats', 'friendship_type_id',
            'estimated_time', 'distance', 'co2', 'description', 'return_trip_id', 'parent_trip_id', 'car_id',
            'weekly_schedule', 'weekly_schedule_time',
        ];
        foreach ($expected as $key) {
            $this->assertArrayHasKey($key, $rules, "Missing create rule key: {$key}");
        }
        foreach (['0', '1'] as $i) {
            foreach (['address', 'json_address', 'lat', 'lng'] as $field) {
                $key = "points.{$i}.{$field}";
                $this->assertArrayHasKey($key, $rules, "Missing create rule key: {$key}");
            }
        }
        Carbon::setTestNow();
    }

    public function test_validator_update_includes_all_documented_rule_keys(): void
    {
        Carbon::setTestNow('2028-01-01 12:00:00');
        $user = User::factory()->create();
        $trip = Trip::factory()->create(['user_id' => $user->id]);
        $payload = [
            'points' => [
                ['address' => 'A', 'json_address' => ['x' => 1], 'lat' => -1.0, 'lng' => -2.0],
                ['address' => 'B', 'json_address' => ['y' => 2], 'lat' => -3.0, 'lng' => -4.0],
            ],
        ];
        $rules = $this->manager()->validator($payload, $user->id, $trip->id)->getRules();
        $expected = [
            'is_passenger', 'from_town', 'to_town', 'trip_date', 'total_seats', 'friendship_type_id',
            'estimated_time', 'distance', 'co2', 'return_trip_id', 'parent_trip_id', 'car_id',
            'weekly_schedule', 'weekly_schedule_time',
        ];
        foreach ($expected as $key) {
            $this->assertArrayHasKey($key, $rules, "Missing update rule key: {$key}");
        }
        foreach (['0', '1'] as $i) {
            foreach (['address', 'json_address', 'lat', 'lng'] as $field) {
                $key = "points.{$i}.{$field}";
                $this->assertArrayHasKey($key, $rules, "Missing update rule key: {$key}");
            }
        }
        Carbon::setTestNow();
    }

    public function test_validator_create_passes_with_future_trip_date_and_points(): void
    {
        Carbon::setTestNow('2028-01-01 12:00:00');
        $user = User::factory()->create();
        $v = $this->manager()->validator($this->minimalCreatePayload(), $user->id);
        $this->assertFalse($v->fails());
        Carbon::setTestNow();
    }

    public function test_validator_create_rejects_trip_date_not_after_now(): void
    {
        Carbon::setTestNow('2028-06-01 12:00:00');
        $user = User::factory()->create();
        $payload = $this->minimalCreatePayload();
        $payload['trip_date'] = '2028-05-01 10:00:00';
        $v = $this->manager()->validator($payload, $user->id);
        $this->assertTrue($v->fails());
        $this->assertTrue($v->errors()->has('trip_date'));
        Carbon::setTestNow();
    }

    public function test_create_returns_null_when_validation_fails(): void
    {
        Carbon::setTestNow('2028-02-01 10:00:00');
        $user = User::factory()->create();
        $manager = $this->manager();
        $this->assertNull($manager->create($user, ['is_passenger' => 0]));
        $this->assertTrue($manager->getErrors()->has('from_town'));
        Carbon::setTestNow();
    }

    public function test_create_rejects_unverified_driver_when_module_requires_verified_drivers(): void
    {
        Carbon::setTestNow('2028-02-01 10:00:00');
        config(['carpoolear.module_validated_drivers' => true]);
        $user = User::factory()->create(['driver_is_verified' => false]);
        $manager = $this->manager();

        $result = $manager->create($user, $this->minimalCreatePayload([
            'is_passenger' => 0,
        ]));

        $this->assertNull($result);
        $this->assertTrue($manager->getErrors()->has('driver_is_verified'));
        Carbon::setTestNow();
    }

    public function test_create_bans_user_when_recent_trip_count_exceeds_configured_limit(): void
    {
        Carbon::setTestNow('2028-02-01 10:00:00');
        config([
            'carpoolear.trip_creation_limits.max_trips' => 0,
            'carpoolear.trip_creation_limits.time_window_hours' => 24,
        ]);
        $user = User::factory()->create(['banned' => 0]);
        Trip::factory()->create([
            'user_id' => $user->id,
            'trip_date' => Carbon::now()->addDays(1),
            'created_at' => Carbon::now()->subHour(),
        ]);

        $manager = $this->manager();
        $result = $manager->create($user, $this->minimalCreatePayload());

        $this->assertNull($result);
        $this->assertTrue($manager->getErrors()->has('banned'));
        $this->assertSame(1, (int) $user->fresh()->banned);
        Carbon::setTestNow();
    }

    public function test_create_bans_user_when_description_contains_banned_word(): void
    {
        Carbon::setTestNow('2028-02-01 10:00:00');
        config(['carpoolear.banned_words_trip_description' => ['whatsapp']]);
        $user = User::factory()->create(['banned' => 0]);
        $manager = $this->manager();

        $result = $manager->create($user, $this->minimalCreatePayload([
            'description' => 'Contact me on WhatsApp now',
        ]));

        $this->assertNull($result);
        $this->assertTrue($manager->getErrors()->has('banned'));
        $this->assertSame(1, (int) $user->fresh()->banned);
        Carbon::setTestNow();
    }

    public function test_create_bans_user_when_description_contains_banned_phone_number(): void
    {
        Carbon::setTestNow('2028-02-01 10:00:00');
        config(['carpoolear.banned_phones' => ['1234567890']]);
        $user = User::factory()->create(['banned' => 0]);
        $manager = $this->manager();

        $result = $manager->create($user, $this->minimalCreatePayload([
            'description' => 'Call me 1234567890',
        ]));

        $this->assertNull($result);
        $this->assertTrue($manager->getErrors()->has('banned'));
        $this->assertSame(1, (int) $user->fresh()->banned);
        Carbon::setTestNow();
    }

    public function test_trip_owner_true_for_driver_and_admin(): void
    {
        $driver = User::factory()->create();
        $admin = User::factory()->create(['is_admin' => true]);
        $other = User::factory()->create();
        $trip = Trip::factory()->create(['user_id' => $driver->id]);

        $m = $this->manager();
        $this->assertTrue($m->tripOwner($driver, $trip));
        $this->assertTrue($m->tripOwner($admin, $trip));
        $this->assertFalse($m->tripOwner($other, $trip));
    }

    public function test_exist_always_returns_true(): void
    {
        $this->assertTrue(TripsManager::exist(12345));
    }

    public function test_price_uses_simple_price_when_api_price_disabled(): void
    {
        config(['carpoolear.api_price' => false, 'carpoolear.fuel_price' => 1000]);
        $manager = $this->manager();
        $distance = 2000;
        $repo = $this->app->make(TripRepository::class);
        $this->assertSame($repo->simplePrice($distance), $manager->price(null, null, $distance));
    }

    public function test_user_can_see_public_trip_as_non_owner(): void
    {
        $driver = User::factory()->create();
        $stranger = User::factory()->create();
        $trip = Trip::factory()->create([
            'user_id' => $driver->id,
            'friendship_type_id' => Trip::PRIVACY_PUBLIC,
        ]);

        $this->assertTrue($this->manager()->userCanSeeTrip($stranger, $trip));
    }

    public function test_user_can_see_friends_trip_only_when_friends(): void
    {
        $driver = User::factory()->create();
        $friend = User::factory()->create();
        $stranger = User::factory()->create();
        $trip = Trip::factory()->create([
            'user_id' => $driver->id,
            'friendship_type_id' => Trip::PRIVACY_FRIENDS,
        ]);

        $this->assertFalse($this->manager()->userCanSeeTrip($stranger, $trip));

        (new FriendsManager(new FriendsRepository))->make($driver, $friend);

        $this->assertTrue($this->manager()->userCanSeeTrip($friend->fresh(), $trip));
    }

    public function test_show_returns_trip_when_user_can_see_it(): void
    {
        $driver = User::factory()->create();
        $viewer = User::factory()->create();
        $trip = Trip::factory()->create([
            'user_id' => $driver->id,
            'friendship_type_id' => Trip::PRIVACY_PUBLIC,
        ]);

        $shown = $this->manager()->show($viewer, $trip->id);
        $this->assertNotNull($shown);
        $this->assertTrue($shown->is($trip));
    }

    public function test_show_returns_null_when_user_cannot_see_private_trip(): void
    {
        $driver = User::factory()->create();
        $stranger = User::factory()->create();
        $trip = Trip::factory()->create([
            'user_id' => $driver->id,
            'friendship_type_id' => Trip::PRIVACY_FRIENDS,
        ]);

        $manager = $this->manager();
        $this->assertNull($manager->show($stranger, $trip->id));
        $this->assertSame('trip_not_foound', $manager->getErrors()['error']);
    }

    public function test_change_trip_seats_updates_when_owner_and_within_bounds(): void
    {
        $driver = User::factory()->create();
        $trip = Trip::factory()->create([
            'user_id' => $driver->id,
            'total_seats' => 2,
        ]);

        $updated = $this->manager()->changeTripSeats($driver, $trip->id, 1);
        $this->assertNotNull($updated);
        $this->assertSame(3, (int) $updated->fresh()->total_seats);
    }

    public function test_change_trip_seats_rejects_when_exceeds_four(): void
    {
        $driver = User::factory()->create();
        $trip = Trip::factory()->create([
            'user_id' => $driver->id,
            'total_seats' => 4,
        ]);

        $manager = $this->manager();
        $this->assertNull($manager->changeTripSeats($driver, $trip->id, 1));
        $this->assertSame('trip_seats_less_than_four', $manager->getErrors()['error']);
    }

    public function test_change_trip_seats_rejects_negative_total(): void
    {
        $driver = User::factory()->create();
        $trip = Trip::factory()->create([
            'user_id' => $driver->id,
            'total_seats' => 1,
        ]);

        $manager = $this->manager();
        $this->assertNull($manager->changeTripSeats($driver, $trip->id, -2));
        $this->assertSame('trip_seats_greater_than_zero', $manager->getErrors()['error']);
    }

    public function test_delete_dispatches_delete_event_and_soft_deletes(): void
    {
        Event::fake([DeleteEvent::class]);
        $driver = User::factory()->create();
        $trip = Trip::factory()->create(['user_id' => $driver->id]);

        $this->assertTrue((bool) $this->manager()->delete($driver, $trip->id));

        Event::assertDispatched(DeleteEvent::class);
        $this->assertNotNull($trip->fresh()->deleted_at);
    }

    public function test_update_rejects_total_seats_below_accepted_passengers(): void
    {
        Carbon::setTestNow('2028-04-01 10:00:00');
        $driver = User::factory()->create();
        $pax1 = User::factory()->create();
        $pax2 = User::factory()->create();
        $trip = Trip::factory()->create([
            'user_id' => $driver->id,
            'total_seats' => 4,
            'friendship_type_id' => Trip::PRIVACY_PUBLIC,
            'trip_date' => Carbon::parse('2028-05-01 12:00:00'),
        ]);

        Passenger::factory()->aceptado()->create(['trip_id' => $trip->id, 'user_id' => $pax1->id]);
        Passenger::factory()->aceptado()->create(['trip_id' => $trip->id, 'user_id' => $pax2->id]);

        $manager = $this->manager();
        $this->assertNull($manager->update($driver, $trip->id, [
            'total_seats' => 1,
        ]));
        $errors = $manager->getErrors();
        $this->assertIsArray($errors);
        $this->assertSame('trip_invalid_seats', $errors['error']);

        Carbon::setTestNow();
    }

    public function test_share_trip_true_when_either_shared_a_ride(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();
        $trip = Trip::factory()->create([
            'user_id' => $a->id,
            'trip_date' => Carbon::now()->addDays(5),
        ]);
        Passenger::factory()->aceptado()->create(['trip_id' => $trip->id, 'user_id' => $b->id]);

        $this->assertTrue($this->manager()->shareTrip($a->fresh(), $b->fresh()));
    }

    public function test_index_delegates_to_search_with_user(): void
    {
        Carbon::setTestNow('2028-05-01 10:00:00');
        $user = User::factory()->create();
        Trip::factory()->create([
            'user_id' => $user->id,
            'friendship_type_id' => Trip::PRIVACY_PUBLIC,
            'trip_date' => Carbon::now()->addDays(10),
        ]);

        $manager = $this->manager();
        $criteria = ['user_id' => $user->id];
        $fromIndex = $manager->index($user, $criteria);
        $fromSearch = $manager->search($user, $criteria);

        $this->assertEquals($fromSearch->count(), $fromIndex->count());
        Carbon::setTestNow();
    }

    public function test_create_aborts_when_get_trip_info_returns_routing_service_unavailable(): void
    {
        Carbon::setTestNow('2028-02-01 10:00:00');
        $user = User::factory()->create(['banned' => 0]);

        $repo = Mockery::mock(TripRepository::class);
        $repo->shouldReceive('getRecentTrips')->once()->with($user->id, 24)->andReturn(collect([]));
        $repo->shouldReceive('getTripInfo')->once()->andReturn(['error_code' => 'routing_service_unavailable']);
        $repo->shouldNotReceive('create');

        Event::fake([CreateEvent::class]);
        $manager = new TripsManager($repo, $this->app->make(UsersManager::class));
        $result = $manager->create($user, $this->minimalCreatePayload());

        $this->assertNull($result);
        $this->assertInstanceOf(\Illuminate\Support\MessageBag::class, $manager->getErrors());
        $this->assertTrue($manager->getErrors()->has('error'));
        Event::assertNotDispatched(CreateEvent::class);
        Carbon::setTestNow();
    }

    public function test_update_aborts_when_get_trip_info_returns_routing_service_unavailable(): void
    {
        Carbon::setTestNow('2028-04-01 10:00:00');
        $driver = User::factory()->create();
        $trip = Trip::factory()->create([
            'user_id' => $driver->id,
            'friendship_type_id' => Trip::PRIVACY_PUBLIC,
            'trip_date' => Carbon::parse('2028-05-01 12:00:00'),
        ]);
        $trip->load('user');

        $repo = Mockery::mock(TripRepository::class);
        $repo->shouldReceive('show')->with(Mockery::on(fn ($u) => $u->is($driver)), $trip->id)->andReturn($trip);
        $repo->shouldReceive('getTripInfo')->once()->andReturn(['error_code' => 'routing_service_unavailable']);
        $repo->shouldNotReceive('update');

        $manager = new TripsManager($repo, $this->app->make(UsersManager::class));
        $result = $manager->update($driver, $trip->id, [
            'points' => [
                ['address' => 'A', 'json_address' => ['x' => 1], 'lat' => -1.0, 'lng' => -2.0],
                ['address' => 'B', 'json_address' => ['y' => 2], 'lat' => -3.0, 'lng' => -4.0],
            ],
        ]);

        $this->assertNull($result);
        $this->assertTrue($manager->getErrors()->has('error'));
        Carbon::setTestNow();
    }

    public function test_create_rejects_unverified_driver_when_is_passenger_is_string_zero(): void
    {
        Carbon::setTestNow('2028-02-01 10:00:00');
        config(['carpoolear.module_validated_drivers' => true]);
        $user = User::factory()->create(['driver_is_verified' => false]);
        $manager = $this->manager();

        $payload = $this->minimalCreatePayload(['is_passenger' => '0']);
        $result = $manager->create($user, $payload);

        $this->assertNull($result);
        $this->assertTrue($manager->getErrors()->has('driver_is_verified'));
        Carbon::setTestNow();
    }

    public function test_create_does_not_ban_when_recent_trip_count_equals_limit(): void
    {
        Carbon::setTestNow('2028-02-01 10:00:00');
        config([
            'carpoolear.trip_creation_limits.max_trips' => 2,
            'carpoolear.trip_creation_limits.time_window_hours' => 24,
        ]);
        $user = User::factory()->create(['banned' => 0]);

        $repo = Mockery::mock(TripRepository::class);
        $repo->shouldReceive('getRecentTrips')->once()->with($user->id, 24)->andReturn(collect([1, 2]));
        $repo->shouldReceive('getTripInfo')->once()->andReturn([]);
        $repo->shouldReceive('create')->once()->andReturnUsing(function (array $data) use ($user) {
            return Trip::factory()->create([
                'user_id' => $data['user_id'] ?? $user->id,
                'from_town' => $data['from_town'] ?? 'X',
                'to_town' => $data['to_town'] ?? 'Y',
                'trip_date' => $data['trip_date'] ?? Carbon::now()->addDays(5),
                'total_seats' => $data['total_seats'] ?? 3,
                'friendship_type_id' => $data['friendship_type_id'] ?? Trip::PRIVACY_PUBLIC,
            ]);
        });

        Event::fake([CreateEvent::class]);
        $manager = new TripsManager($repo, $this->app->make(UsersManager::class));
        $result = $manager->create($user, $this->minimalCreatePayload());

        $this->assertNotNull($result);
        $this->assertSame(0, (int) $user->fresh()->banned);
        Carbon::setTestNow();
    }

    public function test_create_bans_when_recent_trip_count_strictly_exceeds_limit(): void
    {
        Carbon::setTestNow('2028-02-01 10:00:00');
        config([
            'carpoolear.trip_creation_limits.max_trips' => 2,
            'carpoolear.trip_creation_limits.time_window_hours' => 24,
        ]);
        $user = User::factory()->create(['banned' => 0]);

        $repo = Mockery::mock(TripRepository::class);
        $repo->shouldReceive('getRecentTrips')->once()->with($user->id, 24)->andReturn(collect([1, 2, 3]));
        $repo->shouldNotReceive('getTripInfo');
        $repo->shouldNotReceive('create');

        $manager = new TripsManager($repo, $this->app->make(UsersManager::class));
        $result = $manager->create($user, $this->minimalCreatePayload());

        $this->assertNull($result);
        $this->assertTrue($manager->getErrors()->has('banned'));
        $this->assertSame(1, (int) $user->fresh()->banned);
        Carbon::setTestNow();
    }

    public function test_create_logs_trip_limit_diag_lines(): void
    {
        Carbon::setTestNow('2028-02-01 10:00:00');
        config([
            'carpoolear.trip_creation_limits.max_trips' => 1,
            'carpoolear.trip_creation_limits.time_window_hours' => 24,
        ]);
        $user = User::factory()->create(['banned' => 0]);

        $repo = Mockery::mock(TripRepository::class);
        $repo->shouldReceive('getRecentTrips')->andReturn(collect([1, 2]));
        $repo->shouldNotReceive('create');

        Event::fake([MessageLogged::class]);
        $manager = new TripsManager($repo, $this->app->make(UsersManager::class));
        $manager->create($user, $this->minimalCreatePayload());

        Event::assertDispatched(MessageLogged::class, fn (MessageLogged $e) => $e->level === 'info' && str_contains($e->message, 'maxTrips'));
        Event::assertDispatched(MessageLogged::class, fn (MessageLogged $e) => $e->level === 'info' && str_contains($e->message, 'timeWindow'));
        Event::assertDispatched(MessageLogged::class, fn (MessageLogged $e) => $e->level === 'info' && str_contains($e->message, 'recentTrips'));
        Event::assertDispatched(MessageLogged::class, fn (MessageLogged $e) => $e->level === 'info' && str_contains($e->message, 'User banned due to exceeding trip creation limits'));
        Carbon::setTestNow();
    }

    public function test_banned_word_matching_is_case_insensitive_for_configured_word(): void
    {
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

        Carbon::setTestNow('2028-02-01 10:00:00');
        config(['carpoolear.banned_words_trip_description' => ['WhatsApp']]);
        $user = User::factory()->create(['banned' => 0]);
        $manager = $this->manager();

        $result = $manager->create($user, $this->minimalCreatePayload([
            'description' => 'write me on whatsapp please',
        ]));

        $this->assertNull($result);
        $this->assertTrue($manager->getErrors()->has('banned'));
        Carbon::setTestNow();
    }

    public function test_parent_trip_id_sets_return_trip_id_on_parent(): void
    {
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

        Carbon::setTestNow('2028-02-01 10:00:00');
        Event::fake([CreateEvent::class]);
        $user = User::factory()->create();
        $car = Car::factory()->create(['user_id' => $user->id]);
        $manager = $this->manager();

        $parent = $manager->create($user, $this->minimalCreatePayload([
            'car_id' => $car->id,
        ]));
        $this->assertNotNull($parent);

        $child = $manager->create($user, $this->minimalCreatePayload([
            'car_id' => $car->id,
            'trip_date' => '2028-03-11 15:00:00',
            'parent_trip_id' => $parent->id,
        ]));
        $this->assertNotNull($child);
        $this->assertSame($child->id, (int) $parent->fresh()->return_trip_id);
        Carbon::setTestNow();
    }

    public function test_proccess_trips_fills_ciudad_and_provincia_when_missing(): void
    {
        $driver = User::factory()->create();
        $trip = Trip::factory()->create(['user_id' => $driver->id]);
        TripPoint::factory()->create([
            'trip_id' => $trip->id,
            'json_address' => ['name' => 'Rosario', 'state' => 'Santa Fe'],
        ]);
        $trip->load('points');

        $method = new \ReflectionMethod(TripsManager::class, 'proccessTrips');
        $method->setAccessible(true);
        $out = $method->invoke($this->manager(), collect([$trip]));

        $json = $out->first()->points->first()->json_address;
        $this->assertSame('Rosario', $json['ciudad']);
        $this->assertSame('Santa Fe', $json['provincia']);
    }

    public function test_user_can_see_fof_trip_when_viewer_is_friend_of_friend(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();
        $c = User::factory()->create();
        $friends = new FriendsManager(new FriendsRepository);
        $friends->make($a, $b);
        $friends->make($b, $c);

        $trip = Trip::factory()->create([
            'user_id' => $a->id,
            'friendship_type_id' => Trip::PRIVACY_FOF,
            'needs_sellado' => false,
            'state' => Trip::STATE_READY,
        ]);

        $this->assertTrue($this->manager()->userCanSeeTrip($c->fresh(), $trip));
    }

    public function test_user_cannot_see_fof_trip_without_friend_of_friend_link(): void
    {
        $driver = User::factory()->create();
        $stranger = User::factory()->create();
        $trip = Trip::factory()->create([
            'user_id' => $driver->id,
            'friendship_type_id' => Trip::PRIVACY_FOF,
            'needs_sellado' => false,
            'state' => Trip::STATE_READY,
        ]);

        $this->assertFalse($this->manager()->userCanSeeTrip($stranger, $trip));
    }

    public function test_user_can_see_public_trip_false_when_sellado_required_and_not_ready(): void
    {
        $driver = User::factory()->create();
        $stranger = User::factory()->create();
        $trip = Trip::factory()->create([
            'user_id' => $driver->id,
            'friendship_type_id' => Trip::PRIVACY_PUBLIC,
            'needs_sellado' => true,
            'state' => Trip::STATE_AWAITING_PAYMENT,
        ]);

        $this->assertFalse($this->manager()->userCanSeeTrip($stranger, $trip));
    }

    public function test_user_can_see_public_trip_when_passing_trip_id_integer(): void
    {
        $driver = User::factory()->create();
        $stranger = User::factory()->create();
        $trip = Trip::factory()->create([
            'user_id' => $driver->id,
            'friendship_type_id' => Trip::PRIVACY_PUBLIC,
            'needs_sellado' => false,
            'state' => Trip::STATE_READY,
        ]);

        $this->assertTrue($this->manager()->userCanSeeTrip($stranger, $trip->id));
    }

    public function test_delete_sets_tripowner_error_for_non_owner(): void
    {
        $driver = User::factory()->create();
        $stranger = User::factory()->create();
        $trip = Trip::factory()->create(['user_id' => $driver->id]);

        $manager = $this->manager();
        $manager->delete($stranger, $trip->id);

        $this->assertSame(trans('errors.tripowner'), $manager->getErrors());
    }

    public function test_update_sets_tripowner_error_for_non_owner(): void
    {
        Carbon::setTestNow('2028-04-01 10:00:00');
        $driver = User::factory()->create();
        $stranger = User::factory()->create();
        $trip = Trip::factory()->create([
            'user_id' => $driver->id,
            'friendship_type_id' => Trip::PRIVACY_PUBLIC,
            'trip_date' => Carbon::parse('2028-05-01 12:00:00'),
        ]);

        $manager = $this->manager();
        $manager->update($stranger, $trip->id, ['total_seats' => 2]);

        $this->assertSame(trans('errors.tripowner'), $manager->getErrors());
        Carbon::setTestNow();
    }

    public function test_change_visibility_sets_tripowner_error_for_non_owner(): void
    {
        $driver = User::factory()->create();
        $stranger = User::factory()->create();
        $trip = Trip::factory()->create(['user_id' => $driver->id]);

        Event::fake([MessageLogged::class]);
        $manager = $this->manager();
        $manager->changeVisibility($stranger, $trip->id);

        $this->assertSame(trans('errors.tripowner'), $manager->getErrors());
        Event::assertDispatched(MessageLogged::class, fn (MessageLogged $e) => $e->level === 'info' && str_contains($e->message, 'changeVisibility trip'));
    }

    public function test_calc_trip_price_arg_branch_uses_simple_price(): void
    {
        config(['carpoolear.osm_country' => 'ARG', 'carpoolear.fuel_price' => 1000]);
        $manager = $this->manager();
        $distance = 2000;
        $expected = $this->app->make(TripRepository::class)->simplePrice($distance);

        $this->assertSame(
            $expected,
            $manager->calcTripPrice(['name' => 'X'], ['name' => 'Y'], $distance)
        );
    }

    public function test_price_uses_calc_trip_price_when_api_price_enabled_and_endpoints_present(): void
    {
        config([
            'carpoolear.api_price' => true,
            'carpoolear.osm_country' => 'ARG',
            'carpoolear.fuel_price' => 1000,
        ]);
        $manager = $this->manager();
        $distance = 1500;
        $expected = $this->app->make(TripRepository::class)->simplePrice($distance);

        $this->assertSame(
            $expected,
            $manager->price(['name' => 'Origin'], ['name' => 'Dest'], $distance)
        );
    }
}
