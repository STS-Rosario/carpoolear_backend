<?php

namespace Tests\Unit\Repository;

use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Mockery;
use STS\Events\Trip\Create as CreateEvent;
use STS\Models\NodeGeo;
use STS\Models\Passenger;
use STS\Models\PaymentAttempt;
use STS\Models\Route;
use STS\Models\Trip;
use STS\Models\User;
use STS\Repository\TripRepository;
use STS\Services\GeoService;
use STS\Services\MapboxDirectionsRouteService;
use STS\Services\MercadoPagoService;
use Tests\TestCase;

class TripRepositoryTest extends TestCase
{
    private function repo(): TripRepository
    {
        return $this->app->make(TripRepository::class);
    }

    private function makeNode(array $overrides = []): NodeGeo
    {
        $node = new NodeGeo;
        $node->forceFill(array_merge([
            'name' => 'TripN'.substr(uniqid('', true), 0, 6),
            'lat' => -34.5,
            'lng' => -58.5,
            'type' => 'city',
            'state' => 'BA',
            'country' => 'AR',
            'importance' => 1,
        ], $overrides));
        $node->save();

        return $node->fresh();
    }

    private function makeTripRepoPartialForCreate(array $tripInfo, bool $routeNeedsPayment): TripRepository
    {
        $geoService = Mockery::mock(GeoService::class);
        $geoService->shouldReceive('getPaidRegions')->andReturn([]);
        $geoService->shouldReceive('doStopsRequireSellado')->andReturn($routeNeedsPayment);

        $mercadoPagoService = Mockery::mock(MercadoPagoService::class);
        $mapboxService = Mockery::mock(MapboxDirectionsRouteService::class);

        /** @var TripRepository $repo */
        $repo = Mockery::mock(
            TripRepository::class,
            [$geoService, $mercadoPagoService, $mapboxService]
        )->makePartial();

        $repo->shouldReceive('getTripInfo')->andReturn($tripInfo);
        $repo->shouldReceive('addPoints')->andReturnNull();
        $repo->shouldReceive('generateTripPath')->andReturnNull();
        $repo->shouldReceive('generateTripFriendVisibility')->andReturnNull();

        return $repo;
    }

    public function test_show_returns_null_for_soft_deleted_trip_when_user_not_admin(): void
    {
        $user = User::factory()->create();
        $user->forceFill(['is_admin' => false])->saveQuietly();
        $trip = Trip::factory()->create(['user_id' => $user->id]);
        $trip->delete();

        $found = $this->repo()->show($user, $trip->id);

        $this->assertNull($found);
    }

    public function test_show_includes_soft_deleted_trip_for_admin(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->saveQuietly();
        $trip = Trip::factory()->create();
        $trip->delete();

        $found = $this->repo()->show($admin, $trip->id);

        $this->assertNotNull($found);
        $this->assertSame($trip->id, $found->id);
        $this->assertNotNull($found->deleted_at);
        $this->assertTrue($found->relationLoaded('user'));
        $this->assertTrue($found->relationLoaded('points'));
        $this->assertTrue($found->relationLoaded('car'));
        $this->assertTrue($found->relationLoaded('passenger'));
        $this->assertTrue($found->relationLoaded('ratings'));
    }

    public function test_show_for_non_admin_eager_loads_user_and_points_only(): void
    {
        // Mutation intent: preserve non-admin show eager-loading of both user and points.
        // Kills: 5282c105ca53e94c, da0260725194a1cd.
        $user = User::factory()->create();
        $user->forceFill(['is_admin' => false])->saveQuietly();
        $trip = Trip::factory()->create(['user_id' => $user->id]);

        $found = $this->repo()->show($user, $trip->id);

        $this->assertNotNull($found);
        $this->assertTrue($found->relationLoaded('user'));
        $this->assertTrue($found->relationLoaded('points'));
    }

    public function test_index_applies_equality_and_operator_criteria(): void
    {
        $u = User::factory()->create();
        Trip::factory()->create(['user_id' => $u->id, 'total_seats' => 3]);
        Trip::factory()->create(['user_id' => $u->id, 'total_seats' => 5]);

        $byUser = $this->repo()->index([['key' => 'user_id', 'value' => $u->id]]);
        $this->assertCount(2, $byUser);

        $largeOnly = $this->repo()->index([
            ['key' => 'user_id', 'value' => $u->id],
            ['key' => 'total_seats', 'op' => '>', 'value' => 4],
        ]);
        $this->assertCount(1, $largeOnly);
        $this->assertSame(5, (int) $largeOnly->first()->total_seats);
    }

    public function test_index_supports_raw_expression_keys_containing_parenthesis(): void
    {
        // Mutation intent: preserve DB::raw branch when criteria key contains '('.
        // Kills: 016f0f4690481e55.
        $u1 = User::factory()->create();
        $u2 = User::factory()->create();
        Trip::factory()->create(['user_id' => $u1->id, 'total_seats' => 1]);
        Trip::factory()->create(['user_id' => $u2->id, 'total_seats' => 4]);

        $rows = $this->repo()->index([
            ['key' => 'YEAR(trip_date)', 'op' => '>', 'value' => 2000],
        ]);

        $this->assertGreaterThanOrEqual(2, $rows->count());
    }

    public function test_index_applies_withs_only_when_requested(): void
    {
        // Mutation intent: preserve withs guard and eager-load call in index().
        // Kills: af11e441e24a3bdf, 3389b1d9a73e51d3.
        $trip = Trip::factory()->create();

        $withoutWiths = $this->repo()->index([['key' => 'id', 'value' => $trip->id]]);
        $this->assertFalse($withoutWiths->first()->relationLoaded('user'));

        $withUser = $this->repo()->index([['key' => 'id', 'value' => $trip->id]], ['user']);
        $this->assertTrue($withUser->first()->relationLoaded('user'));
    }

    public function test_delete_soft_deletes_trip(): void
    {
        $trip = Trip::factory()->create();
        $this->assertTrue($this->repo()->delete($trip));
        $this->assertSoftDeleted('trips', ['id' => $trip->id]);
    }

    public function test_add_points_delete_points_and_generate_trip_path(): void
    {
        $trip = Trip::factory()->create();
        $repo = $this->repo();

        $repo->addPoints($trip, [
            ['lat' => -34.6, 'lng' => -58.4, 'json_address' => ['id' => 501, 'ciudad' => 'Origen']],
            ['lat' => -34.7, 'lng' => -58.5, 'json_address' => ['id' => 502, 'ciudad' => 'Destino']],
        ]);

        $trip->refresh();
        $this->assertCount(2, $trip->points);

        $path = $repo->generateTripPath($trip);
        $this->assertSame('.501.502.', $path);
        $this->assertSame('.501.502.', $trip->fresh()->path);

        $repo->deletePoints($trip);
        $this->assertCount(0, $trip->fresh()->points);
    }

    public function test_generate_trip_path_ignores_zero_or_negative_node_ids(): void
    {
        // Mutation intent: keep strict > 0 node-id filter in generateTripPath().
        // Kills: f1e3920c718877f8, 461b2b2b2014b253, 81ebf36ce14d0d8e.
        $trip = Trip::factory()->create();
        $repo = $this->repo();

        $repo->addPoints($trip, [
            ['lat' => -34.60, 'lng' => -58.40, 'json_address' => ['id' => -5, 'ciudad' => 'Neg']],
            ['lat' => -34.59, 'lng' => -58.39, 'json_address' => ['id' => 0, 'ciudad' => 'Zero']],
            ['lat' => -34.58, 'lng' => -58.38, 'json_address' => ['id' => 2, 'ciudad' => 'Two']],
            ['lat' => -34.57, 'lng' => -58.37, 'json_address' => ['id' => 1, 'ciudad' => 'One']],
        ]);

        $path = $repo->generateTripPath($trip);

        $this->assertSame('.2.1.', $path);
        $this->assertSame('.2.1.', $trip->fresh()->path);
    }

    public function test_simple_price_uses_fuel_config(): void
    {
        Config::set('carpoolear.fuel_price', 2000);

        $price = $this->repo()->simplePrice(5000);

        $this->assertEqualsWithDelta(10000.0, (float) $price, 0.0001);
    }

    public function test_get_trip_by_trip_passenger_returns_passenger_by_primary_key(): void
    {
        $trip = Trip::factory()->create();
        $passengerUser = User::factory()->create();
        $p = Passenger::factory()->aceptado()->create([
            'trip_id' => $trip->id,
            'user_id' => $passengerUser->id,
        ]);

        $found = $this->repo()->getTripByTripPassenger($p->id);

        $this->assertNotNull($found);
        $this->assertSame($p->id, $found->id);
        $this->assertSame($trip->id, $found->trip_id);
    }

    public function test_sellado_viaje_reflects_trip_count_and_threshold(): void
    {
        Config::set('carpoolear.module_trip_creation_payment_enabled', true);
        Config::set('carpoolear.module_trip_creation_payment_trips_threshold', 2);

        $user = User::factory()->create();
        Trip::factory()->count(1)->create(['user_id' => $user->id]);

        $info = $this->repo()->selladoViaje($user);
        $this->assertTrue($info['trip_creation_payment_enabled']);
        $this->assertSame(2, $info['free_trips_amount']);
        $this->assertSame(1, $info['trips_created_by_user_amount']);
        $this->assertFalse($info['user_over_free_limit']);

        Trip::factory()->create(['user_id' => $user->id]);
        $info2 = $this->repo()->selladoViaje($user);
        $this->assertTrue($info2['user_over_free_limit']);
    }

    public function test_get_recent_trips_filters_by_created_at_window(): void
    {
        $user = User::factory()->create();
        $recent = Trip::factory()->create(['user_id' => $user->id]);
        $old = Trip::factory()->create(['user_id' => $user->id]);
        $old->forceFill(['created_at' => Carbon::now()->subDays(3)])->saveQuietly();

        $rows = $this->repo()->getRecentTrips($user->id, 48);

        $this->assertCount(1, $rows);
        $this->assertSame($recent->id, $rows->first()->id);
    }

    public function test_hide_trips_and_unhide_trips_for_sentinel_soft_delete(): void
    {
        $user = User::factory()->create();
        $future = Trip::factory()->create([
            'user_id' => $user->id,
            'trip_date' => Carbon::now()->addWeek(),
            'weekly_schedule' => 0,
        ]);

        $this->repo()->hideTrips($user);

        $future->refresh();
        $this->assertNotNull($future->deleted_at);

        $this->repo()->unhideTrips($user);

        $future->refresh();
        $this->assertNull($future->deleted_at);
    }

    public function test_share_trip_true_when_other_is_accepted_passenger_on_active_driver_trip(): void
    {
        $driver = User::factory()->create();
        $other = User::factory()->create();
        $trip = Trip::factory()->create([
            'user_id' => $driver->id,
            'trip_date' => Carbon::now()->addDay(),
            'weekly_schedule' => 0,
        ]);
        Passenger::factory()->aceptado()->create([
            'trip_id' => $trip->id,
            'user_id' => $other->id,
        ]);

        $this->assertTrue($this->repo()->shareTrip($driver, $other));
        $this->assertFalse($this->repo()->shareTrip($driver, User::factory()->create()));
    }

    public function test_get_trips_driver_returns_future_trips_for_user(): void
    {
        $user = User::factory()->create();
        $active = Trip::factory()->create([
            'user_id' => $user->id,
            'trip_date' => Carbon::now()->addDay(),
            'weekly_schedule' => 0,
        ]);
        $later = Trip::factory()->create([
            'user_id' => $user->id,
            'trip_date' => Carbon::now()->addDays(2),
            'weekly_schedule' => 0,
        ]);
        Trip::factory()->create([
            'user_id' => $user->id,
            'trip_date' => Carbon::now()->subDay(),
            'weekly_schedule' => 0,
        ]);
        Trip::factory()->create([
            'user_id' => User::factory()->create()->id,
            'trip_date' => Carbon::now()->addDay(),
            'weekly_schedule' => 0,
        ]);

        $trips = $this->repo()->getTrips($user, $user->id, true);

        $this->assertCount(2, $trips);
        $this->assertSame($active->id, $trips->first()->id);
        $this->assertSame($later->id, $trips->last()->id);
        $this->assertTrue($trips->first()->relationLoaded('user'));
        $this->assertTrue($trips->first()->relationLoaded('points'));
        $this->assertTrue($trips->first()->relationLoaded('passengerAccepted'));
        $this->assertTrue($trips->first()->relationLoaded('car'));
        $this->assertTrue($trips->first()->passengerAccepted->every(
            fn ($p) => $p->relationLoaded('user')
        ));
    }

    public function test_get_trips_passenger_returns_trips_where_user_accepted(): void
    {
        $driver = User::factory()->create();
        $passenger = User::factory()->create();
        $trip = Trip::factory()->create([
            'user_id' => $driver->id,
            'trip_date' => Carbon::now()->addDay(),
        ]);
        $laterAccepted = Trip::factory()->create([
            'user_id' => $driver->id,
            'trip_date' => Carbon::now()->addDays(2),
        ]);
        $deletedAccepted = Trip::factory()->create([
            'user_id' => $driver->id,
            'trip_date' => Carbon::now()->addDays(3),
        ]);
        $deletedAccepted->delete();
        $requestedNotAccepted = Trip::factory()->create([
            'user_id' => $driver->id,
            'trip_date' => Carbon::now()->addDays(4),
        ]);
        $otherPassengerTrip = Trip::factory()->create([
            'user_id' => $driver->id,
            'trip_date' => Carbon::now()->addDays(5),
        ]);

        Passenger::factory()->aceptado()->create([
            'trip_id' => $trip->id,
            'user_id' => $passenger->id,
        ]);
        Passenger::factory()->aceptado()->create([
            'trip_id' => $laterAccepted->id,
            'user_id' => $passenger->id,
        ]);
        Passenger::factory()->aceptado()->create([
            'trip_id' => $deletedAccepted->id,
            'user_id' => $passenger->id,
        ]);
        Passenger::factory()->create([
            'trip_id' => $requestedNotAccepted->id,
            'user_id' => $passenger->id,
            'request_state' => Passenger::STATE_PENDING,
        ]);
        Passenger::factory()->aceptado()->create([
            'trip_id' => $otherPassengerTrip->id,
            'user_id' => User::factory()->create()->id,
        ]);

        $trips = $this->repo()->getTrips($passenger, $passenger->id, false);

        $this->assertCount(2, $trips);
        $this->assertSame($trip->id, $trips->first()->id);
        $this->assertSame($laterAccepted->id, $trips->last()->id);
        $this->assertTrue($trips->first()->relationLoaded('user'));
        $this->assertTrue($trips->first()->relationLoaded('points'));
        $this->assertTrue($trips->first()->relationLoaded('passengerAccepted'));
        $this->assertTrue($trips->first()->relationLoaded('car'));
        $this->assertTrue($trips->first()->passengerAccepted->every(
            fn ($p) => $p->relationLoaded('user')
        ));
    }

    public function test_get_old_trips_excludes_weekly_schedule_and_past_only(): void
    {
        $user = User::factory()->create();
        $past = Trip::factory()->create([
            'user_id' => $user->id,
            'trip_date' => Carbon::now()->subWeek(),
            'weekly_schedule' => 0,
        ]);
        $olderPast = Trip::factory()->create([
            'user_id' => $user->id,
            'trip_date' => Carbon::now()->subWeeks(2),
            'weekly_schedule' => 0,
        ]);
        Trip::factory()->create([
            'user_id' => $user->id,
            'trip_date' => Carbon::now()->subWeek(),
            'weekly_schedule' => Trip::DAY_MONDAY,
        ]);
        Trip::factory()->create([
            'user_id' => $user->id,
            'trip_date' => Carbon::now()->addWeek(),
            'weekly_schedule' => 0,
        ]);

        $trips = $this->repo()->getOldTrips($user, $user->id, true);

        $this->assertCount(2, $trips);
        $this->assertSame($olderPast->id, $trips->first()->id);
        $this->assertSame($past->id, $trips->last()->id);
        $this->assertTrue($trips->first()->relationLoaded('user'));
        $this->assertTrue($trips->first()->relationLoaded('points'));
        $this->assertTrue($trips->first()->relationLoaded('passengerAccepted'));
        $this->assertTrue($trips->first()->relationLoaded('car'));
        $this->assertTrue($trips->first()->passengerAccepted->every(
            fn ($p) => $p->relationLoaded('user')
        ));
    }

    public function test_search_filters_by_user_id_and_paginates(): void
    {
        $owner = User::factory()->create();
        $viewer = User::factory()->create();
        $visible = Trip::factory()->create([
            'user_id' => $owner->id,
            'friendship_type_id' => Trip::PRIVACY_PUBLIC,
            'state' => Trip::STATE_READY,
            'trip_date' => Carbon::now()->addDay(),
        ]);
        Trip::factory()->create([
            'user_id' => User::factory()->create()->id,
            'friendship_type_id' => Trip::PRIVACY_PUBLIC,
            'state' => Trip::STATE_READY,
            'trip_date' => Carbon::now()->addDay(),
        ]);

        $page = $this->repo()->search($viewer, [
            'user_id' => $owner->id,
            'page' => 1,
            'page_size' => 5,
        ]);

        $ids = collect($page->items())->pluck('id');
        $this->assertTrue($ids->contains($visible->id));
        $this->assertSame(1, $ids->count());
    }

    public function test_search_applies_is_passenger_filter_and_keeps_routes_eager_loaded(): void
    {
        // Mutation intent: preserve base routes eager load and is_passenger filter in search().
        // Kills: 41b3ff3a3b90d001, 812d135b57a68dc6.
        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->saveQuietly();

        $match = Trip::factory()->create([
            'is_passenger' => 1,
            'friendship_type_id' => Trip::PRIVACY_PUBLIC,
            'state' => Trip::STATE_READY,
            'needs_sellado' => 0,
            'trip_date' => Carbon::now()->addDay(),
        ]);
        Trip::factory()->create([
            'is_passenger' => 0,
            'friendship_type_id' => Trip::PRIVACY_PUBLIC,
            'state' => Trip::STATE_READY,
            'needs_sellado' => 0,
            'trip_date' => Carbon::now()->addDay(),
        ]);

        $page = $this->repo()->search($admin, [
            'is_passenger' => 'true',
            'page' => 1,
            'page_size' => 10,
        ]);

        $items = collect($page->items());
        $this->assertSame(1, $items->count());
        $this->assertSame($match->id, $items->first()->id);
        $this->assertTrue($items->first()->relationLoaded('routes'));
    }

    public function test_search_with_admin_flag_controls_trashed_and_supports_single_date_bounds(): void
    {
        // Mutation intent: preserve admin-withTrashed gate and from/to date OR-branch handling.
        // Kills: adb8efe4d6d7b5c5, fe67f74c929986cf, 45c5a98301e1e6bc, e38c3f8f43a330bb, 8415cdc00871d360.
        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->saveQuietly();

        $trashed = Trip::factory()->create([
            'friendship_type_id' => Trip::PRIVACY_PUBLIC,
            'state' => Trip::STATE_READY,
            'needs_sellado' => 0,
            'trip_date' => Carbon::now()->subHours(2),
        ]);
        $trashed->delete();

        $future = Trip::factory()->create([
            'friendship_type_id' => Trip::PRIVACY_PUBLIC,
            'state' => Trip::STATE_READY,
            'needs_sellado' => 0,
            'trip_date' => Carbon::now()->addHours(3),
        ]);
        $old = Trip::factory()->create([
            'friendship_type_id' => Trip::PRIVACY_PUBLIC,
            'state' => Trip::STATE_READY,
            'needs_sellado' => 0,
            'trip_date' => Carbon::now()->subDays(4),
        ]);

        $fromDate = Carbon::now()->subDay()->format('Y-m-d');
        $toDate = Carbon::now()->subHour()->format('Y-m-d');

        $withoutAdminFlag = $this->repo()->search($admin, [
            'from_date' => $fromDate,
            'page' => 1,
            'page_size' => 20,
        ]);
        $withoutIds = collect($withoutAdminFlag->items())->pluck('id');
        $this->assertFalse($withoutIds->contains($trashed->id));
        $this->assertTrue($withoutIds->contains($future->id));

        $withAdminFlag = $this->repo()->search($admin, [
            'is_admin' => 'true',
            'from_date' => $fromDate,
            'to_date' => $toDate,
            'page' => 1,
            'page_size' => 20,
        ]);
        $withIds = collect($withAdminFlag->items())->pluck('id');
        $this->assertTrue($withIds->contains($trashed->id));
        $this->assertFalse($withIds->contains($future->id));
        $this->assertFalse($withIds->contains($old->id));
    }

    public function test_get_potential_node_private_bbox_logic_uses_lat_lng_ranges(): void
    {
        // Mutation intent: keep +/- delta math and bbox comparator setup in getPotentialNode().
        // Kills: 47a4022bfb577c5a, a50255ec77726d09, 33b99591f429010d, 7ab8d1032746dbfa,
        //        c971ded4c0583849, dd8743ead8f87fde, 14701d7b0afa6c43, 9e6cb9312e8e70ea,
        //        e0900113fa619282, fce18506ce2a3b67, 2f31f58e34bc7f68, bbdfa6dd5309dc76,
        //        b687fb0c758f9ff7, 1294a7e0b757765f, 13bffdc93611a1bd, 2561f9ba60928e7c,
        //        aa1ba96b77874b63, bb988e6606ef287b, 64854536d7731d76, 926f5eb76fa1c5d2,
        //        ee8770e106619a2a, 86a3522102bd856b, 4abda33c3ccae2f5, 0280f49e5e9aa5f6,
        //        168a1c682d274fec, 9332ef5aa60d7c7b, 726f02044afec66a, 74f70ee8c58fdc8d.
        $point = ['lat' => -34.60, 'lng' => -58.40];
        $inside = $this->makeNode(['lat' => -34.58, 'lng' => -58.33, 'name' => 'TripInside']);
        $outsideLat = $this->makeNode(['lat' => -34.80, 'lng' => -58.33, 'name' => 'TripOutsideLat']);
        $outsideLng = $this->makeNode(['lat' => -34.58, 'lng' => -58.15, 'name' => 'TripOutsideLng']);

        $method = new \ReflectionMethod(TripRepository::class, 'getPotentialNode');
        $method->setAccessible(true);

        $found = $method->invoke($this->repo(), $point);

        $this->assertNotNull($found);
        $this->assertSame($inside->id, $found->id);
        $this->assertNotSame($outsideLat->id, $found->id);
        $this->assertNotSame($outsideLng->id, $found->id);
    }

    public function test_generate_trip_friend_visibility_fof_and_friends_only_branches_insert_rows(): void
    {
        // Mutation intent: keep privacy branching and SQL insert calls in generateTripFriendVisibility().
        // Kills: 5656ee9b01173343, 85d71c45fe613abd, 18c59a50fd2ffaeb, 7bfc0ab3a97dea41,
        //        7242bbee8560e5a1, 853a1c66244b9770, 37e68b0071fa371b, 53c27d17ceefefc7,
        //        92a7cb8cf0ce32c2, 7409a27f9aff2ae1, 53d5810f9a02f52f, 3aab526f6920f8c0.
        $driver = User::factory()->create();
        $friend = User::factory()->create();
        $friendOfFriend = User::factory()->create();
        $stranger = User::factory()->create();

        $driver->allFriends()->attach($friend->id, ['state' => User::FRIEND_ACCEPTED]);
        $friend->allFriends()->attach($friendOfFriend->id, ['state' => User::FRIEND_ACCEPTED]);
        $driver->allFriends()->attach($stranger->id, ['state' => User::FRIEND_REQUEST]);

        $fofTrip = Trip::factory()->create([
            'user_id' => $driver->id,
            'friendship_type_id' => Trip::PRIVACY_FOF,
            'trip_date' => Carbon::now()->addDay(),
        ]);
        $friendsTrip = Trip::factory()->create([
            'user_id' => $driver->id,
            'friendship_type_id' => Trip::PRIVACY_FRIENDS,
            'trip_date' => Carbon::now()->addDay(),
        ]);

        $repo = $this->repo();
        $repo->generateTripFriendVisibility($fofTrip);
        $repo->generateTripFriendVisibility($friendsTrip);

        $this->assertDatabaseHas('user_visibility_trip', ['user_id' => $friend->id, 'trip_id' => $fofTrip->id]);
        $this->assertDatabaseHas('user_visibility_trip', ['user_id' => $friendOfFriend->id, 'trip_id' => $fofTrip->id]);
        $this->assertDatabaseHas('user_visibility_trip', ['user_id' => $friend->id, 'trip_id' => $friendsTrip->id]);
        $this->assertDatabaseMissing('user_visibility_trip', ['user_id' => $friendOfFriend->id, 'trip_id' => $friendsTrip->id]);
        $this->assertDatabaseMissing('user_visibility_trip', ['user_id' => $stranger->id, 'trip_id' => $fofTrip->id]);
    }

    public function test_create_caps_seat_price_at_maximum_when_module_enabled(): void
    {
        // Mutation intent: keep max-price guard, cap math and assignment in create().
        // Kills: a7a74d3095388168, b06e7346ab0b7439, 4be0f43c62497887, 0213b9ad10d10530,
        //        5554cc2241bfb082, 022258a4506e3501, a83b5da5b120f035, 6938af9e22ede182,
        //        05e9c3d11001b524, a740b8f320284c77, 9d95fa6e7a3a63ff, da58e72d61dacac0,
        //        4ac785b2268a41f5, 4a534627977977e3, 071ba109676bd985.
        Config::set('carpoolear.module_max_price_enabled', true);
        Config::set('carpoolear.module_trip_creation_payment_enabled', false);

        $tripInfo = [
            'status' => true,
            'data' => [
                'maximum_trip_price_cents' => 1000,
                'recommended_trip_price_cents' => 700,
            ],
        ];
        $repo = $this->makeTripRepoPartialForCreate($tripInfo, false);
        $user = User::factory()->create();

        $trip = $repo->create([
            'user_id' => $user->id,
            'is_passenger' => 0,
            'from_town' => 'A',
            'to_town' => 'B',
            'trip_date' => Carbon::now()->addHour(),
            'total_seats' => 1,
            'friendship_type_id' => Trip::PRIVACY_PUBLIC,
            'estimated_time' => '01:00',
            'distance' => 10,
            'co2' => 1,
            'description' => 'test',
            'mail_send' => false,
            'seat_price_cents' => 900,
            'points' => [
                ['lat' => -34.6, 'lng' => -58.4, 'json_address' => ['id' => 501, 'ciudad' => 'Origen']],
            ],
        ]);

        $trip->refresh();
        $this->assertSame(500, (int) $trip->seat_price_cents);
        $this->assertSame(700, (int) $trip->recommended_trip_price_cents);
    }

    public function test_create_keeps_seat_price_when_cap_not_required_or_module_disabled(): void
    {
        // Mutation intent: preserve branches that skip cap assignment when not applicable.
        // Kills: c420b4523347fb9b, 054956c69594930e, 3bc7a0ac2cd873e1, 4bc087b221ea14d1,
        //        330f86c4e850f0f9, d3a6deda630c0225, 1301ca6a05995d26, 9b0a406f82a305f3,
        //        989e733cfc9ee959, ebad617f35c5726b, e416ada5cc106318, 85d42995c6d515f4.
        Config::set('carpoolear.module_max_price_enabled', false);
        Config::set('carpoolear.module_trip_creation_payment_enabled', false);

        $tripInfo = [
            'status' => true,
            'data' => [
                'maximum_trip_price_cents' => 1000,
                'recommended_trip_price_cents' => 650,
            ],
        ];
        $repo = $this->makeTripRepoPartialForCreate($tripInfo, false);
        $user = User::factory()->create();

        $trip = $repo->create([
            'user_id' => $user->id,
            'is_passenger' => 0,
            'from_town' => 'A',
            'to_town' => 'B',
            'trip_date' => Carbon::now()->addHour(),
            'total_seats' => 1,
            'friendship_type_id' => Trip::PRIVACY_PUBLIC,
            'estimated_time' => '01:00',
            'distance' => 10,
            'co2' => 1,
            'description' => 'test',
            'mail_send' => false,
            'seat_price_cents' => 900,
            'points' => [
                ['lat' => -34.6, 'lng' => -58.4, 'json_address' => ['id' => 501, 'ciudad' => 'Origen']],
            ],
        ]);

        $trip->refresh();
        $this->assertSame(900, (int) $trip->seat_price_cents);
        $this->assertSame(650, (int) $trip->recommended_trip_price_cents);
    }

    public function test_create_sets_awaiting_payment_and_preference_when_route_requires_sellado(): void
    {
        // Mutation intent: keep route payment requirement pipeline and awaiting-payment state transition.
        // Kills: 97d4c2cdee6ca23d, e53a5df21a05bb4e, 360811401013af7d, 4123fa9d994e5a5b,
        //        1a6d2f2a81d01d31, 2b10aa7ab3776cd5, 41ad6e0ea218715e, ebbc4167c595fae9, d25ccf133496e175.
        Config::set('carpoolear.module_max_price_enabled', false);
        Config::set('carpoolear.module_trip_creation_payment_enabled', true);
        Config::set('carpoolear.module_trip_creation_payment_trips_threshold', 1);
        Config::set('carpoolear.module_trip_creation_payment_amount_cents', 1800);

        $geoService = Mockery::mock(GeoService::class);
        $geoService->shouldReceive('getPaidRegions')->andReturn([]);
        $geoService->shouldReceive('doStopsRequireSellado')->andReturn(true);

        $mercadoPagoService = Mockery::mock(MercadoPagoService::class);
        $mercadoPagoService->shouldReceive('createPaymentPreferenceForSellado')
            ->once()
            ->andReturn((object) [
                'id' => 'pref_123',
                'init_point' => 'https://pay.test/pref_123',
            ]);

        $mapboxService = Mockery::mock(MapboxDirectionsRouteService::class);

        /** @var TripRepository $repo */
        $repo = Mockery::mock(
            TripRepository::class,
            [$geoService, $mercadoPagoService, $mapboxService]
        )->makePartial();
        $repo->shouldReceive('getTripInfo')->andReturn([
            'status' => true,
            'data' => [
                'maximum_trip_price_cents' => 1000,
                'recommended_trip_price_cents' => 700,
            ],
        ]);
        $repo->shouldReceive('addPoints')->andReturnNull();
        $repo->shouldReceive('generateTripPath')->andReturnNull();
        $repo->shouldReceive('generateTripFriendVisibility')->andReturnNull();

        $user = User::factory()->create();
        // Force threshold branch by creating an existing trip.
        Trip::factory()->create(['user_id' => $user->id]);

        $trip = $repo->create([
            'user_id' => $user->id,
            'is_passenger' => 0,
            'from_town' => 'A',
            'to_town' => 'B',
            'trip_date' => Carbon::now()->addHour(),
            'total_seats' => 1,
            'friendship_type_id' => Trip::PRIVACY_PUBLIC,
            'estimated_time' => '01:00',
            'distance' => 10,
            'co2' => 1,
            'description' => 'test',
            'mail_send' => false,
            'seat_price_cents' => 900,
            'points' => [
                ['lat' => -34.6, 'lng' => -58.4, 'json_address' => ['id' => 501, 'ciudad' => 'Origen']],
                ['lat' => -34.5, 'lng' => -58.3, 'json_address' => ['id' => 502, 'ciudad' => 'Destino']],
            ],
        ]);

        $this->assertSame('https://pay.test/pref_123', $trip->payment_url);

        $trip->refresh();
        $this->assertSame(Trip::STATE_AWAITING_PAYMENT, $trip->state);
        $this->assertSame('pref_123', $trip->payment_id);
        $this->assertTrue((bool) $trip->needs_sellado);
    }

    public function test_create_creates_and_syncs_routes_from_points_json_address_ids(): void
    {
        // Mutation intent: keep route-loop iteration and endpoint extraction from points in create().
        // Kills: 0080679657fc2dd9, e0b1a2010fa804e9, 26405b9a722298d8, 0e7b59bfbe29fe09,
        //        ab3144e108c0e760, 74b84d645bbeaaa3, 79ecceb2aeec68e0, e478c1e7b52a8b76,
        //        a62aa6e9192c3220, d7148f2bcf5c0dd0, a54deebcf1014a06, 490aec5db92a5b6e,
        //        e705f32825014ed3, f07be5a076a1191f, cbefb735437de83c.
        Config::set('carpoolear.module_max_price_enabled', false);
        Config::set('carpoolear.module_trip_creation_payment_enabled', false);

        $repo = $this->makeTripRepoPartialForCreate([
            'status' => true,
            'data' => [
                'maximum_trip_price_cents' => 1000,
                'recommended_trip_price_cents' => 650,
            ],
        ], false);

        $from = $this->makeNode(['name' => 'RouteFrom', 'lat' => -34.60, 'lng' => -58.40]);
        $to = $this->makeNode(['name' => 'RouteTo', 'lat' => -34.50, 'lng' => -58.30]);
        $user = User::factory()->create();

        $trip = $repo->create([
            'user_id' => $user->id,
            'is_passenger' => 0,
            'from_town' => 'A',
            'to_town' => 'B',
            'trip_date' => Carbon::now()->addHour(),
            'total_seats' => 2,
            'friendship_type_id' => Trip::PRIVACY_PUBLIC,
            'estimated_time' => '01:00',
            'distance' => 10,
            'co2' => 1,
            'description' => 'test',
            'mail_send' => false,
            'points' => [
                ['lat' => -34.6, 'lng' => -58.4, 'json_address' => ['id' => $from->id, 'ciudad' => 'Origen']],
                ['lat' => -34.5, 'lng' => -58.3, 'json_address' => ['id' => $to->id, 'ciudad' => 'Destino']],
            ],
        ]);

        $trip->refresh();
        $this->assertCount(1, $trip->routes);
        $this->assertSame($from->id, (int) $trip->routes->first()->from_id);
        $this->assertSame($to->id, (int) $trip->routes->first()->to_id);

        $createdRoute = Route::query()->where('from_id', $from->id)->where('to_id', $to->id)->first();
        $this->assertNotNull($createdRoute);
        $this->assertFalse((bool) $createdRoute->processed);
        $this->assertCount(2, $createdRoute->nodes);
        $this->assertTrue($createdRoute->nodes->pluck('id')->contains($from->id));
        $this->assertTrue($createdRoute->nodes->pluck('id')->contains($to->id));
    }

    public function test_create_reuses_processed_route_and_dispatches_create_event(): void
    {
        // Mutation intent: preserve existing-route branch, processed event trigger and trip_routes sync.
        // Kills: c3ab61b50d2e29e6, 4e2796064a396e48, 2de3deecc5bf329b, 52810aad74f43a6a, 7afd019bd08af694.
        Event::fake();
        Config::set('carpoolear.module_max_price_enabled', false);
        Config::set('carpoolear.module_trip_creation_payment_enabled', false);

        $repo = $this->makeTripRepoPartialForCreate([
            'status' => true,
            'data' => [
                'maximum_trip_price_cents' => 1000,
                'recommended_trip_price_cents' => 650,
            ],
        ], false);

        $from = $this->makeNode(['name' => 'ExistingRouteFrom', 'lat' => -34.41, 'lng' => -58.21]);
        $to = $this->makeNode(['name' => 'ExistingRouteTo', 'lat' => -34.42, 'lng' => -58.22]);
        $existingRoute = new Route;
        $existingRoute->from_id = $from->id;
        $existingRoute->to_id = $to->id;
        $existingRoute->processed = true;
        $existingRoute->save();
        $existingRoute->nodes()->sync([$from->id, $to->id]);

        $user = User::factory()->create();
        $trip = $repo->create([
            'user_id' => $user->id,
            'is_passenger' => 0,
            'from_town' => 'A',
            'to_town' => 'B',
            'trip_date' => Carbon::now()->addHour(),
            'total_seats' => 2,
            'friendship_type_id' => Trip::PRIVACY_PUBLIC,
            'estimated_time' => '01:00',
            'distance' => 10,
            'co2' => 1,
            'description' => 'test',
            'mail_send' => false,
            'points' => [
                ['lat' => -34.41, 'lng' => -58.21, 'json_address' => ['id' => $from->id, 'ciudad' => 'Origen']],
                ['lat' => -34.42, 'lng' => -58.22, 'json_address' => ['id' => $to->id, 'ciudad' => 'Destino']],
            ],
        ]);

        Event::assertDispatched(CreateEvent::class);
        $this->assertSame(1, Route::query()->where('from_id', $from->id)->where('to_id', $to->id)->count());

        $trip->refresh();
        $this->assertCount(1, $trip->routes);
        $this->assertSame($existingRoute->id, (int) $trip->routes->first()->id);
    }

    public function test_update_skips_new_payment_when_old_route_already_required_sellado(): void
    {
        // Mutation intent: preserve old-points count guard and lat/lng mapping for old-route sellado check.
        // Kills: b06214e1744b47ae, 778fc72ecde2c4b5, e86bce3766a81131, 4cd0f45423a259c2,
        //        1a556c3e6c5d37a6, 852234564b8b2945, 8c6e8823c8e66b93.
        Config::set('carpoolear.module_trip_creation_payment_enabled', true);
        Config::set('carpoolear.module_trip_creation_payment_trips_threshold', 1);
        Config::set('carpoolear.module_trip_creation_payment_amount_cents', 1800);

        $geoService = Mockery::mock(GeoService::class);
        $geoService->shouldReceive('getPaidRegions')->andReturn([]);
        $geoService->shouldReceive('doStopsRequireSellado')->twice()->andReturn(true, true);

        $mercadoPagoService = Mockery::mock(MercadoPagoService::class);
        $mercadoPagoService->shouldReceive('createPaymentPreferenceForSellado')->never();

        $mapboxService = Mockery::mock(MapboxDirectionsRouteService::class);

        /** @var TripRepository $repo */
        $repo = Mockery::mock(
            TripRepository::class,
            [$geoService, $mercadoPagoService, $mapboxService]
        )->makePartial();
        $repo->shouldReceive('getTripInfo')->andReturn(['status' => false]);

        $user = User::factory()->create();
        Trip::factory()->create(['user_id' => $user->id]); // force threshold
        $trip = Trip::factory()->create([
            'user_id' => $user->id,
            'state' => Trip::STATE_READY,
            'needs_sellado' => false,
        ]);

        $repo->addPoints($trip, [
            ['lat' => -34.60, 'lng' => -58.40, 'json_address' => ['id' => 701, 'ciudad' => 'OldA']],
            ['lat' => -34.59, 'lng' => -58.39, 'json_address' => ['id' => 702, 'ciudad' => 'OldB']],
        ]);

        $updated = $repo->update($trip, [
            'points' => [
                ['lat' => -34.61, 'lng' => -58.41, 'json_address' => ['id' => 703, 'ciudad' => 'NewA']],
                ['lat' => -34.58, 'lng' => -58.38, 'json_address' => ['id' => 704, 'ciudad' => 'NewB']],
            ],
        ]);

        $updated->refresh();
        $this->assertSame(Trip::STATE_READY, $updated->state);
        $this->assertFalse((bool) $updated->needs_sellado);
        $this->assertNull($updated->payment_id);
    }

    public function test_update_caps_price_with_payload_total_seats_and_rounding_rules(): void
    {
        // Mutation intent: preserve update cap guards/coalesce and max-seat rounding/comparison behavior.
        // Kills: 829b7c5c1cd18c62, 973452f72b559beb, d4c9e20293c2c15c, 36118027b79c2e79,
        //        13792bbf188a3fc8, e99dba43208657df, ac0c7cb7c8c84488, a8347453eee474e0,
        //        013110593ed7a715, 67c5f5cedbe5f96e, c7aac5f5db44cfc4, 1669a15c00663974,
        //        1103c83ab827cb26, 0f09716a552e0cc8, a03e507eb14a99f0, 27d3cb8394955dee.
        Config::set('carpoolear.module_max_price_enabled', true);
        Config::set('carpoolear.module_trip_creation_payment_enabled', false);

        $geoService = Mockery::mock(GeoService::class);
        $geoService->shouldReceive('getPaidRegions')->andReturn([]);
        $geoService->shouldReceive('doStopsRequireSellado')->andReturn(false);

        $mercadoPagoService = Mockery::mock(MercadoPagoService::class);
        $mapboxService = Mockery::mock(MapboxDirectionsRouteService::class);

        /** @var TripRepository $repo */
        $repo = Mockery::mock(
            TripRepository::class,
            [$geoService, $mercadoPagoService, $mapboxService]
        )->makePartial();
        $repo->shouldReceive('getTripInfo')->andReturn(
            ['status' => true, 'data' => ['maximum_trip_price_cents' => 1003, 'recommended_trip_price_cents' => 777]],
            ['status' => true, 'data' => ['maximum_trip_price_cents' => 1001, 'recommended_trip_price_cents' => 778]],
            ['status' => true, 'data' => ['maximum_trip_price_cents' => 1003, 'recommended_trip_price_cents' => 779]],
        );

        $trip = Trip::factory()->create([
            'total_seats' => 1,
            'seat_price_cents' => 900,
            'state' => Trip::STATE_READY,
        ]);
        $repo->addPoints($trip, [
            ['lat' => -34.60, 'lng' => -58.40, 'json_address' => ['id' => 801, 'ciudad' => 'A']],
            ['lat' => -34.59, 'lng' => -58.39, 'json_address' => ['id' => 802, 'ciudad' => 'B']],
        ]);

        // 1003 / (3 + 1) = 250.75 -> round = 251; must use payload total_seats (3), not trip->total_seats (1).
        $updated1 = $repo->update($trip, [
            'total_seats' => 3,
            'seat_price_cents' => 900,
            'points' => [
                ['lat' => -34.61, 'lng' => -58.41, 'json_address' => ['id' => 803, 'ciudad' => 'C']],
                ['lat' => -34.58, 'lng' => -58.38, 'json_address' => ['id' => 804, 'ciudad' => 'D']],
            ],
        ]);
        $updated1->refresh();
        $this->assertSame(251, (int) $updated1->seat_price_cents);

        // 1001 / (3 + 1) = 250.25 -> round = 250 (different from ceil=251).
        $updated2 = $repo->update($trip->fresh(), [
            'total_seats' => 3,
            'seat_price_cents' => 900,
            'points' => [
                ['lat' => -34.62, 'lng' => -58.42, 'json_address' => ['id' => 805, 'ciudad' => 'E']],
                ['lat' => -34.57, 'lng' => -58.37, 'json_address' => ['id' => 806, 'ciudad' => 'F']],
            ],
        ]);
        $updated2->refresh();
        $this->assertSame(250, (int) $updated2->seat_price_cents);

        // Below cap should remain unchanged (protects `>` branch against <= mutation).
        $updated3 = $repo->update($trip->fresh(), [
            'total_seats' => 3,
            'seat_price_cents' => 240,
            'points' => [
                ['lat' => -34.63, 'lng' => -58.43, 'json_address' => ['id' => 807, 'ciudad' => 'G']],
                ['lat' => -34.56, 'lng' => -58.36, 'json_address' => ['id' => 808, 'ciudad' => 'H']],
            ],
        ]);
        $updated3->refresh();
        $this->assertSame(240, (int) $updated3->seat_price_cents);
    }

    public function test_update_replaces_points_persists_recommended_price_and_maps_payment_coords(): void
    {
        // Mutation intent: preserve recommended-price save, point replacement/path regeneration and lat/lng payment mapping.
        // Kills: 504111811d143a35, adb294f1bb793f6c, 9a5ded833c49409e, efaf1746ee6762ca,
        //        99996c05fe1ad1cc, 8e05f298d0ad73bd, d95e5bc6b1d33820, 48013067c3ce547d.
        Config::set('carpoolear.module_trip_creation_payment_enabled', false);
        Config::set('carpoolear.module_max_price_enabled', false);

        $oldPointsForSellado = null;
        $newPointsForSellado = null;

        $geoService = Mockery::mock(GeoService::class);
        $geoService->shouldReceive('getPaidRegions')->andReturn([]);
        $geoService->shouldReceive('doStopsRequireSellado')
            ->twice()
            ->andReturnUsing(function (array $points) use (&$oldPointsForSellado, &$newPointsForSellado) {
                if ($oldPointsForSellado === null) {
                    $oldPointsForSellado = $points;
                } else {
                    $newPointsForSellado = $points;
                }

                return false;
            });

        $mercadoPagoService = Mockery::mock(MercadoPagoService::class);
        $mapboxService = Mockery::mock(MapboxDirectionsRouteService::class);

        /** @var TripRepository $repo */
        $repo = Mockery::mock(
            TripRepository::class,
            [$geoService, $mercadoPagoService, $mapboxService]
        )->makePartial();
        $repo->shouldReceive('getTripInfo')->andReturn([
            'status' => true,
            'data' => [
                'recommended_trip_price_cents' => 654,
            ],
        ]);

        $trip = Trip::factory()->create([
            'recommended_trip_price_cents' => 100,
            'state' => Trip::STATE_READY,
        ]);
        $repo->addPoints($trip, [
            ['lat' => -34.60, 'lng' => -58.40, 'json_address' => ['id' => 901, 'ciudad' => 'OldA']],
            ['lat' => -34.59, 'lng' => -58.39, 'json_address' => ['id' => 902, 'ciudad' => 'OldB']],
        ]);

        $updated = $repo->update($trip, [
            'points' => [
                ['lat' => -34.55, 'lng' => -58.35, 'json_address' => ['id' => 903, 'ciudad' => 'NewA']],
                ['lat' => -34.54, 'lng' => -58.34, 'json_address' => ['id' => 904, 'ciudad' => 'NewB']],
            ],
        ]);

        $updated->refresh();
        $this->assertSame(654, (int) $updated->recommended_trip_price_cents);
        $this->assertSame('.903.904.', $updated->path);
        $this->assertCount(2, $updated->points);
        $this->assertSame([903, 904], $updated->points->pluck('json_address.id')->values()->all());

        $this->assertSame([[-34.6, -58.4], [-34.59, -58.39]], $oldPointsForSellado);
        $this->assertSame([[-34.55, -58.35], [-34.54, -58.34]], $newPointsForSellado);
    }

    public function test_update_triggers_new_sellado_payment_on_non_paid_to_paid_transition(): void
    {
        // Mutation intent: preserve strict gate condition for non-paid->paid transition and threshold boundary.
        // Kills: 961cc23104e94a97, 879b7f380fb694b8, 447b75ab63a85945, 189953df07063235,
        //        c29223e821b10dea, ba5bfa4ac32943cd.
        Config::set('carpoolear.module_trip_creation_payment_enabled', true);
        Config::set('carpoolear.module_trip_creation_payment_trips_threshold', 2);
        Config::set('carpoolear.module_trip_creation_payment_amount_cents', 1800);
        Config::set('carpoolear.module_max_price_enabled', false);

        $geoService = Mockery::mock(GeoService::class);
        $geoService->shouldReceive('getPaidRegions')->andReturn([]);
        $geoService->shouldReceive('doStopsRequireSellado')->twice()->andReturn(false, true);

        $mercadoPagoService = Mockery::mock(MercadoPagoService::class);
        $mercadoPagoService->shouldReceive('createPaymentPreferenceForSellado')->never();

        $mapboxService = Mockery::mock(MapboxDirectionsRouteService::class);

        /** @var TripRepository $repo */
        $repo = Mockery::mock(
            TripRepository::class,
            [$geoService, $mercadoPagoService, $mapboxService]
        )->makePartial();
        $repo->shouldReceive('getTripInfo')->andReturn(['status' => false]);

        $user = User::factory()->create();
        $trip = Trip::factory()->create([
            'user_id' => $user->id,
            'state' => Trip::STATE_READY,
            'payment_id' => 'existing_pref',
            'needs_sellado' => false,
        ]);
        Trip::factory()->create(['user_id' => $user->id]); // count == threshold boundary (2)

        $repo->addPoints($trip, [
            ['lat' => -34.60, 'lng' => -58.40, 'json_address' => ['id' => 1001, 'ciudad' => 'OldA']],
            ['lat' => -34.59, 'lng' => -58.39, 'json_address' => ['id' => 1002, 'ciudad' => 'OldB']],
        ]);

        $updated = $repo->update($trip, [
            'points' => [
                ['lat' => -34.50, 'lng' => -58.30, 'json_address' => ['id' => 1003, 'ciudad' => 'NewA']],
                ['lat' => -34.49, 'lng' => -58.29, 'json_address' => ['id' => 1004, 'ciudad' => 'NewB']],
            ],
        ]);

        $updated->refresh();
        $this->assertSame(Trip::STATE_AWAITING_PAYMENT, $updated->state);
        $this->assertSame('existing_pref', $updated->payment_id);
        $this->assertTrue((bool) $updated->needs_sellado);
    }

    public function test_update_does_not_trigger_sellado_when_module_disabled_even_if_route_changes_to_paid(): void
    {
        // Mutation intent: prevent relaxed boolean OR gates in update sellado trigger condition.
        // Kills: 36c540d16cc674f5, 681e7bcd48bf7b7f, b50e9041e39aac2e, 77b52754499d420c, 59c98589c9da49eb.
        Config::set('carpoolear.module_trip_creation_payment_enabled', false);
        Config::set('carpoolear.module_trip_creation_payment_trips_threshold', 2);
        Config::set('carpoolear.module_trip_creation_payment_amount_cents', 1800);
        Config::set('carpoolear.module_max_price_enabled', false);

        $geoService = Mockery::mock(GeoService::class);
        $geoService->shouldReceive('getPaidRegions')->andReturn([]);
        $geoService->shouldReceive('doStopsRequireSellado')->twice()->andReturn(false, true);

        $mercadoPagoService = Mockery::mock(MercadoPagoService::class);
        $mercadoPagoService->shouldReceive('createPaymentPreferenceForSellado')->never();

        $mapboxService = Mockery::mock(MapboxDirectionsRouteService::class);

        /** @var TripRepository $repo */
        $repo = Mockery::mock(
            TripRepository::class,
            [$geoService, $mercadoPagoService, $mapboxService]
        )->makePartial();
        $repo->shouldReceive('getTripInfo')->andReturn(['status' => false]);

        $user = User::factory()->create();
        $trip = Trip::factory()->create([
            'user_id' => $user->id,
            'state' => Trip::STATE_READY,
            'payment_id' => null,
            'needs_sellado' => false,
        ]);
        Trip::factory()->create(['user_id' => $user->id]); // count == threshold boundary (2)

        $repo->addPoints($trip, [
            ['lat' => -34.60, 'lng' => -58.40, 'json_address' => ['id' => 1101, 'ciudad' => 'OldA']],
            ['lat' => -34.59, 'lng' => -58.39, 'json_address' => ['id' => 1102, 'ciudad' => 'OldB']],
        ]);

        $updated = $repo->update($trip, [
            'points' => [
                ['lat' => -34.50, 'lng' => -58.30, 'json_address' => ['id' => 1103, 'ciudad' => 'NewA']],
                ['lat' => -34.49, 'lng' => -58.29, 'json_address' => ['id' => 1104, 'ciudad' => 'NewB']],
            ],
        ]);

        $updated->refresh();
        $this->assertSame(Trip::STATE_READY, $updated->state);
        $this->assertNull($updated->payment_id);
        $this->assertFalse((bool) $updated->needs_sellado);
    }

    public function test_update_marks_sellado_needed_without_new_preference_when_completed_payment_exists(): void
    {
        // Mutation intent: preserve selladoAlreadyPaid branch in update (set needs_sellado + persist).
        // Kills: a38f3aa02684a67a, 8f0f4900b81196c4, 3922f273b08381d3.
        Config::set('carpoolear.module_trip_creation_payment_enabled', true);
        Config::set('carpoolear.module_trip_creation_payment_trips_threshold', 1);
        Config::set('carpoolear.module_trip_creation_payment_amount_cents', 1800);
        Config::set('carpoolear.module_max_price_enabled', false);

        $geoService = Mockery::mock(GeoService::class);
        $geoService->shouldReceive('getPaidRegions')->andReturn([]);
        $geoService->shouldReceive('doStopsRequireSellado')->twice()->andReturn(false, true);

        $mercadoPagoService = Mockery::mock(MercadoPagoService::class);
        $mercadoPagoService->shouldReceive('createPaymentPreferenceForSellado')->never();

        $mapboxService = Mockery::mock(MapboxDirectionsRouteService::class);

        /** @var TripRepository $repo */
        $repo = Mockery::mock(
            TripRepository::class,
            [$geoService, $mercadoPagoService, $mapboxService]
        )->makePartial();
        $repo->shouldReceive('getTripInfo')->andReturn(['status' => false]);

        $user = User::factory()->create();
        $trip = Trip::factory()->create([
            'user_id' => $user->id,
            'state' => Trip::STATE_READY,
            'payment_id' => 'paid_pref_1',
            'needs_sellado' => false,
        ]);
        Trip::factory()->create(['user_id' => $user->id]); // satisfy threshold
        PaymentAttempt::query()->create([
            'trip_id' => $trip->id,
            'user_id' => $user->id,
            'payment_id' => 'paid_pref_1',
            'payment_status' => PaymentAttempt::STATUS_COMPLETED,
            'amount_cents' => 1800,
        ]);

        $repo->addPoints($trip, [
            ['lat' => -34.60, 'lng' => -58.40, 'json_address' => ['id' => 1201, 'ciudad' => 'OldA']],
            ['lat' => -34.59, 'lng' => -58.39, 'json_address' => ['id' => 1202, 'ciudad' => 'OldB']],
        ]);

        $updated = $repo->update($trip, [
            'points' => [
                ['lat' => -34.50, 'lng' => -58.30, 'json_address' => ['id' => 1203, 'ciudad' => 'NewA']],
                ['lat' => -34.49, 'lng' => -58.29, 'json_address' => ['id' => 1204, 'ciudad' => 'NewB']],
            ],
        ]);

        $updated->refresh();
        $this->assertSame(Trip::STATE_READY, $updated->state);
        $this->assertSame('paid_pref_1', $updated->payment_id);
        $this->assertTrue((bool) $updated->needs_sellado);
    }

    public function test_update_clears_payment_state_when_route_no_longer_needs_sellado(): void
    {
        // Mutation intent: preserve module-enabled && !routeNeedsPayment elseif behavior and payment-state reset list.
        // Kills: 45f27ca691ab564d, 5bdf31da7f173533, 2c4d45590fbb4491, 2095960def72d398,
        //        f9febe68e921584f, 05f9966842a91f2c, 5e5980987ced1592, 2ff5b8a00050b688, 2b73a9ae7652cdd8.
        Config::set('carpoolear.module_trip_creation_payment_enabled', true);
        Config::set('carpoolear.module_trip_creation_payment_trips_threshold', 1);
        Config::set('carpoolear.module_max_price_enabled', false);
        if (! Schema::hasColumn('trips', 'payment_url')) {
            Schema::table('trips', function (Blueprint $table): void {
                $table->string('payment_url')->nullable();
            });
        }

        $geoService = Mockery::mock(GeoService::class);
        $geoService->shouldReceive('getPaidRegions')->andReturn([]);
        $call = 0;
        $geoService->shouldReceive('doStopsRequireSellado')
            ->times(6)
            ->andReturnUsing(function () use (&$call): bool {
                $call++;

                return $call % 2 === 1;
            });

        $mercadoPagoService = Mockery::mock(MercadoPagoService::class);
        $mapboxService = Mockery::mock(MapboxDirectionsRouteService::class);

        /** @var TripRepository $repo */
        $repo = Mockery::mock(
            TripRepository::class,
            [$geoService, $mercadoPagoService, $mapboxService]
        )->makePartial();
        $repo->shouldReceive('getTripInfo')->andReturn(['status' => false]);

        $user = User::factory()->create();
        Trip::factory()->create(['user_id' => $user->id]);

        $statesToReset = [
            Trip::STATE_AWAITING_PAYMENT,
            Trip::STATE_PENDING_PAYMENT,
            Trip::STATE_PAYMENT_FAILED,
        ];

        foreach ($statesToReset as $idx => $state) {
            $trip = Trip::factory()->create([
                'user_id' => $user->id,
                'state' => $state,
                'payment_id' => 'pref_to_clear_'.$idx,
                'needs_sellado' => true,
            ]);

            $repo->addPoints($trip, [
                ['lat' => -34.60, 'lng' => -58.40, 'json_address' => ['id' => 1301 + $idx * 10, 'ciudad' => 'OldA']],
                ['lat' => -34.59, 'lng' => -58.39, 'json_address' => ['id' => 1302 + $idx * 10, 'ciudad' => 'OldB']],
            ]);

            $updated = $repo->update($trip, [
                'points' => [
                    ['lat' => -34.70, 'lng' => -58.50, 'json_address' => ['id' => 1303 + $idx * 10, 'ciudad' => 'NewA']],
                    ['lat' => -34.69, 'lng' => -58.49, 'json_address' => ['id' => 1304 + $idx * 10, 'ciudad' => 'NewB']],
                ],
            ]);

            $updated->refresh();
            $this->assertFalse((bool) $updated->needs_sellado);
            $this->assertNull($updated->payment_id);
            $this->assertSame(Trip::STATE_READY, $updated->state);
        }
    }
}
