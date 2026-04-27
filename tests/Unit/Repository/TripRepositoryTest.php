<?php

namespace Tests\Unit\Repository;

use Carbon\Carbon;
use Illuminate\Support\Facades\Config;
use STS\Models\Passenger;
use STS\Models\Trip;
use STS\Models\User;
use STS\Repository\TripRepository;
use Tests\TestCase;

class TripRepositoryTest extends TestCase
{
    private function repo(): TripRepository
    {
        return $this->app->make(TripRepository::class);
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
        Trip::factory()->create([
            'user_id' => $user->id,
            'trip_date' => Carbon::now()->subDay(),
            'weekly_schedule' => 0,
        ]);

        $trips = $this->repo()->getTrips($user, $user->id, true);

        $this->assertCount(1, $trips);
        $this->assertSame($active->id, $trips->first()->id);
    }

    public function test_get_trips_passenger_returns_trips_where_user_accepted(): void
    {
        $driver = User::factory()->create();
        $passenger = User::factory()->create();
        $trip = Trip::factory()->create([
            'user_id' => $driver->id,
            'trip_date' => Carbon::now()->addDay(),
        ]);
        Passenger::factory()->aceptado()->create([
            'trip_id' => $trip->id,
            'user_id' => $passenger->id,
        ]);

        $trips = $this->repo()->getTrips($passenger, $passenger->id, false);

        $this->assertCount(1, $trips);
        $this->assertSame($trip->id, $trips->first()->id);
    }

    public function test_get_old_trips_excludes_weekly_schedule_and_past_only(): void
    {
        $user = User::factory()->create();
        $past = Trip::factory()->create([
            'user_id' => $user->id,
            'trip_date' => Carbon::now()->subWeek(),
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

        $this->assertCount(1, $trips);
        $this->assertSame($past->id, $trips->first()->id);
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
}
