<?php

namespace Tests\Feature\Http;

use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use STS\Helpers\IdentityValidationHelper;
use STS\Models\NodeGeo;
use STS\Models\Passenger;
use STS\Models\RouteCache;
use STS\Models\Trip;
use STS\Models\TripSearch;
use STS\Models\User;
use Tests\TestCase;

class TripControllerIntegrationTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('carpoolear.module_validated_drivers', false);
        Config::set('carpoolear.api_price', false);
        Config::set('carpoolear.fuel_price', 1000);
        Config::set('carpoolear.module_trip_creation_payment_enabled', false);
        Config::set('carpoolear.trip_route_cache_bypass', false);
        Config::set('carpoolear.trip_creation_limits.max_trips', 100);
        Config::set('carpoolear.trip_creation_limits.time_window_hours', 24);
    }

    private function enableStrictNewUserIdentityEnforcement(): void
    {
        config([
            'carpoolear.identity_validation_enabled' => true,
            'carpoolear.identity_validation_optional' => false,
            'carpoolear.identity_validation_required_new_users' => true,
            'carpoolear.identity_validation_new_users_date' => '2000-01-01',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function minimalCreatePayload(): array
    {
        return [
            'is_passenger' => 0,
            'from_town' => 'Origin Town',
            'to_town' => 'Destination Town',
            'trip_date' => '2028-03-10 15:00:00',
            'total_seats' => 3,
            'friendship_type_id' => Trip::PRIVACY_PUBLIC,
            'estimated_time' => '01:30',
            'distance' => 100,
            'co2' => 10,
            'description' => 'integration trip',
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
        ];
    }

    private function seedRouteCacheForMinimalCreatePoints(): void
    {
        $points = $this->minimalCreatePayload()['points'];
        $hash = hash('sha256', json_encode($points));
        RouteCache::query()->where('hashed_points', $hash)->delete();
        RouteCache::query()->create([
            'points' => $points,
            'route_data' => [
                'status' => true,
                'data' => [
                    'distance' => 50_000,
                    'duration' => 3600,
                    'recommended_trip_price_cents' => 500,
                ],
                'message' => 'OK',
            ],
            'expires_at' => now()->addDay(),
        ]);
    }

    /**
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    private function withOldCordovaUserAgent(callable $callback)
    {
        $keys = ['HTTP_SEC_CH_UA', 'HTTP_USER_AGENT', 'HTTP_X_APP_PLATFORM', 'HTTP_X_APP_VERSION'];
        $saved = [];
        foreach ($keys as $key) {
            $saved[$key] = $_SERVER[$key] ?? null;
        }

        $_SERVER['HTTP_SEC_CH_UA'] = '"Chromium";v="110", "WebView"';
        $_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (Linux; Android 10) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/110.0.0.0 Mobile Safari/537.36';
        unset($_SERVER['HTTP_X_APP_PLATFORM'], $_SERVER['HTTP_X_APP_VERSION']);

        try {
            return $callback();
        } finally {
            foreach ($keys as $key) {
                if ($saved[$key] === null) {
                    unset($_SERVER[$key]);
                } else {
                    $_SERVER[$key] = $saved[$key];
                }
            }
        }
    }

    public function test_trip_mutating_routes_require_authentication(): void
    {
        $trip = Trip::factory()->create();

        $checks = [
            ['POST', '/api/trips'],
            ['PUT', "/api/trips/{$trip->id}"],
            ['DELETE', "/api/trips/{$trip->id}"],
            ['GET', "/api/trips/{$trip->id}"],
            ['POST', "/api/trips/{$trip->id}/changeSeats"],
            ['POST', "/api/trips/{$trip->id}/change-visibility"],
            ['GET', '/api/users/my-trips'],
            ['GET', '/api/users/my-old-trips'],
            ['GET', '/api/users/sellado-viaje'],
            ['POST', '/api/trips/price'],
            ['POST', '/api/trips/trip-info'],
        ];

        foreach ($checks as [$method, $uri]) {
            $this->json($method, $uri, $method === 'POST' ? [] : [])
                ->assertUnauthorized()
                ->assertJsonPath('message', 'Unauthorized.');
        }
    }

    public function test_search_allows_guests_and_returns_paginated_envelope(): void
    {
        $this->getJson('/api/trips')
            ->assertOk()
            ->assertJsonStructure(['data', 'meta' => ['pagination' => ['total', 'per_page', 'current_page', 'total_pages']]])
            ->assertJsonPath('meta.pagination.per_page', 20);
    }

    public function test_search_respects_custom_page_size(): void
    {
        $this->getJson('/api/trips?page_size=7')
            ->assertOk()
            ->assertJsonPath('meta.pagination.per_page', 7);
    }

    public function test_search_with_origin_persists_trip_search_row(): void
    {
        $origin = NodeGeo::query()->create([
            'name' => 'TripSearchOrigin',
            'lat' => -34.6,
            'lng' => -58.4,
            'type' => 'city',
        ]);

        $user = User::factory()->create();
        $before = TripSearch::query()->count();

        $this->actingAs($user, 'api')
            ->getJson('/api/trips?origin_id='.$origin->id.'&is_passenger=true')
            ->assertOk();

        $this->assertSame($before + 1, TripSearch::query()->count());
        $row = TripSearch::query()->latest('id')->first();
        $this->assertSame((int) $origin->id, (int) $row->origin_id);
        $this->assertTrue((bool) $row->is_passenger);
        $this->assertSame($user->id, (int) $row->user_id);
    }

    public function test_search_returns_legacy_placeholder_payload_for_old_cordova_clients(): void
    {
        $this->withOldCordovaUserAgent(function () {
            $this->getJson('/api/trips')
                ->assertOk()
                ->assertJsonPath('data.0.from_town', 'ACTUALIZA TU APP')
                ->assertJsonPath('meta.pagination.total', 1);
        });
    }

    public function test_show_returns_legacy_placeholder_for_old_cordova_clients(): void
    {
        $user = User::factory()->create();
        $trip = Trip::factory()->create(['user_id' => $user->id]);

        $this->withOldCordovaUserAgent(function () use ($user, $trip) {
            $this->actingAs($user, 'api')
                ->getJson("/api/trips/{$trip->id}")
                ->assertOk()
                ->assertJsonPath('data.id', 0)
                ->assertJsonPath('data.from_town', 'ACTUALIZA TU APP');
        });
    }

    public function test_create_returns_unprocessable_when_identity_required_and_user_not_validated(): void
    {
        $this->enableStrictNewUserIdentityEnforcement();
        Carbon::setTestNow('2028-02-01 10:00:00');

        $user = User::factory()->create([
            'is_admin' => false,
            'identity_validated' => false,
            'validate_by_date' => null,
        ]);

        $this->actingAs($user, 'api')
            ->postJson('/api/trips', $this->minimalCreatePayload())
            ->assertUnprocessable()
            ->assertJsonPath('message', IdentityValidationHelper::identityValidationRequiredMessage());

        Carbon::setTestNow();
    }

    public function test_create_returns_unprocessable_when_validation_fails(): void
    {
        Carbon::setTestNow('2028-02-01 10:00:00');
        $user = User::factory()->create(['identity_validated' => true]);

        $this->actingAs($user, 'api')
            ->postJson('/api/trips', [])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Could not create new trip.');

        Carbon::setTestNow();
    }

    public function test_create_returns_trip_payload_when_validation_and_route_cache_succeed(): void
    {
        Carbon::setTestNow('2028-02-01 10:00:00');
        $this->seedRouteCacheForMinimalCreatePoints();
        Http::fake();

        $user = User::factory()->create(['identity_validated' => true]);

        $response = $this->actingAs($user, 'api')
            ->postJson('/api/trips', $this->minimalCreatePayload());

        $response->assertOk();
        $response->assertJsonStructure(['data' => ['id', 'from_town', 'to_town']]);
        $tripId = (int) $response->json('data.id');
        $this->assertDatabaseHas('trips', [
            'id' => $tripId,
            'user_id' => $user->id,
        ]);

        Carbon::setTestNow();
    }

    public function test_update_returns_trip_payload_when_owner_updates_description(): void
    {
        Carbon::setTestNow('2028-04-01 12:00:00');
        $owner = User::factory()->create();
        $trip = Trip::factory()->create([
            'user_id' => $owner->id,
            'friendship_type_id' => Trip::PRIVACY_PUBLIC,
            'trip_date' => Carbon::parse('2028-05-15 10:00:00'),
        ]);

        $this->actingAs($owner, 'api')
            ->putJson("/api/trips/{$trip->id}", ['description' => 'Updated by owner'])
            ->assertOk()
            ->assertJsonPath('data.id', $trip->id)
            ->assertJsonPath('data.description', 'Updated by owner');

        Carbon::setTestNow();
    }

    public function test_update_returns_unprocessable_when_user_does_not_own_trip(): void
    {
        Carbon::setTestNow('2028-04-01 12:00:00');
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $trip = Trip::factory()->create([
            'user_id' => $owner->id,
            'trip_date' => Carbon::parse('2028-05-15 10:00:00'),
        ]);

        $this->actingAs($stranger, 'api')
            ->putJson("/api/trips/{$trip->id}", ['description' => 'nope'])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Could not update trip.');

        Carbon::setTestNow();
    }

    public function test_delete_returns_ok_envelope_when_owner_deletes_trip(): void
    {
        $owner = User::factory()->create();
        $trip = Trip::factory()->create(['user_id' => $owner->id]);

        $this->actingAs($owner, 'api')
            ->deleteJson("/api/trips/{$trip->id}")
            ->assertOk()
            ->assertExactJson(['data' => 'ok']);

        $this->assertNotNull($trip->fresh()->deleted_at);
    }

    public function test_show_returns_trip_when_visibility_allows(): void
    {
        $driver = User::factory()->create();
        $viewer = User::factory()->create();
        $trip = Trip::factory()->create([
            'user_id' => $driver->id,
            'friendship_type_id' => Trip::PRIVACY_PUBLIC,
        ]);

        $this->actingAs($viewer, 'api')
            ->getJson("/api/trips/{$trip->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $trip->id);
    }

    public function test_show_returns_unprocessable_when_trip_is_not_visible(): void
    {
        $driver = User::factory()->create();
        $stranger = User::factory()->create();
        $trip = Trip::factory()->create([
            'user_id' => $driver->id,
            'friendship_type_id' => Trip::PRIVACY_FRIENDS,
        ]);

        $this->actingAs($stranger, 'api')
            ->getJson("/api/trips/{$trip->id}")
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Could not found trip.');
    }

    public function test_change_trip_seats_returns_trip_when_increment_succeeds(): void
    {
        $owner = User::factory()->create();
        $trip = Trip::factory()->create([
            'user_id' => $owner->id,
            'total_seats' => 2,
        ]);

        $this->actingAs($owner, 'api')
            ->postJson("/api/trips/{$trip->id}/changeSeats", ['increment' => 1])
            ->assertOk()
            ->assertJsonPath('data.total_seats', 3);
    }

    public function test_change_trip_seats_returns_unprocessable_when_increment_invalid(): void
    {
        $owner = User::factory()->create();
        $trip = Trip::factory()->create([
            'user_id' => $owner->id,
            'total_seats' => 4,
        ]);

        $this->actingAs($owner, 'api')
            ->postJson("/api/trips/{$trip->id}/changeSeats", ['increment' => 1])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Could not update trip.');
    }

    public function test_change_visibility_returns_trip_payload_for_admin(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $driver = User::factory()->create();
        $trip = Trip::factory()->create(['user_id' => $driver->id]);

        $this->actingAs($admin, 'api')
            ->postJson("/api/trips/{$trip->id}/change-visibility")
            ->assertOk()
            ->assertJsonPath('data.id', $trip->id);
    }

    public function test_get_trips_lists_driver_trips_and_supports_as_driver_false(): void
    {
        $user = User::factory()->create();
        $asDriver = Trip::factory()->create(['user_id' => $user->id]);
        $asPassengerTrip = Trip::factory()->create();
        Passenger::factory()->aceptado()->create([
            'trip_id' => $asPassengerTrip->id,
            'user_id' => $user->id,
        ]);

        $this->actingAs($user, 'api')
            ->getJson('/api/users/my-trips?as_driver=true')
            ->assertOk()
            ->assertJsonStructure(['data'])
            ->assertJsonFragment(['id' => $asDriver->id]);

        $this->actingAs($user, 'api')
            ->getJson('/api/users/my-trips?as_driver=false')
            ->assertOk()
            ->assertJsonFragment(['id' => $asPassengerTrip->id]);
    }

    public function test_admin_can_list_trips_for_another_user_via_user_id(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $other = User::factory()->create();
        $trip = Trip::factory()->create(['user_id' => $other->id]);

        $this->actingAs($admin, 'api')
            ->getJson('/api/users/my-trips?user_id='.$other->id)
            ->assertOk()
            ->assertJsonFragment(['id' => $trip->id]);
    }

    public function test_get_old_trips_returns_past_trips_for_requested_user(): void
    {
        $user = User::factory()->create();
        $past = Trip::factory()->create([
            'user_id' => $user->id,
            'trip_date' => Carbon::now()->subDays(3),
        ]);
        Trip::factory()->create([
            'user_id' => $user->id,
            'trip_date' => Carbon::now()->addDays(5),
        ]);

        $this->actingAs($user, 'api')
            ->getJson('/api/users/my-old-trips?user_id='.$user->id)
            ->assertOk()
            ->assertJsonFragment(['id' => $past->id]);
    }

    public function test_price_endpoint_returns_numeric_estimate_for_distance(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'api')
            ->postJson('/api/trips/price', ['distance' => 2000]);

        $response->assertOk();
        $payload = $response->decodeResponseJson()->json();
        $this->assertIsNumeric($payload);
        $this->assertSame(2000.0, (float) $payload);
    }

    public function test_trip_info_endpoint_returns_route_shape_when_route_cache_hit(): void
    {
        $points = [
            ['lat' => -35.1, 'lng' => -59.1, 'address' => 'A', 'json_address' => ['name' => 'A']],
            ['lat' => -35.2, 'lng' => -59.2, 'address' => 'B', 'json_address' => ['name' => 'B']],
        ];
        $hash = hash('sha256', json_encode($points));
        RouteCache::query()->where('hashed_points', $hash)->delete();
        RouteCache::query()->create([
            'points' => $points,
            'route_data' => [
                'status' => true,
                'data' => ['distance' => 1000, 'duration' => 120],
                'message' => 'OK',
            ],
            'expires_at' => now()->addDay(),
        ]);
        Http::fake();

        $user = User::factory()->create();

        $this->actingAs($user, 'api')
            ->postJson('/api/trips/trip-info', ['points' => $points])
            ->assertOk()
            ->assertJsonPath('status', true);
    }

    public function test_sellado_viaje_returns_success_envelope_with_threshold_fields(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'api')
            ->getJson('/api/users/sellado-viaje')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => [
                'trip_creation_payment_enabled',
                'free_trips_amount',
                'trips_created_by_user_amount',
                'user_over_free_limit',
            ]]);
    }
}
