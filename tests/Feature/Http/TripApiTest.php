<?php

namespace Tests\Feature\Http;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Mockery as m;
use ReflectionMethod;
use STS\Http\Controllers\Api\v1\TripController;
use STS\Models\Trip;
use STS\Models\User;
use STS\Repository\TripSearchRepository;
use STS\Services\Logic\TripsManager;
use Tests\TestCase;

class TripApiTest extends TestCase
{
    protected $tripsLogic;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tripsLogic = $this->mock(\STS\Services\Logic\TripsManager::class);
    }

    protected function tearDown(): void
    {
        m::close();
        parent::tearDown();
    }

    protected function parseJson($response)
    {
        return json_decode($response->getContent());
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

    public function test_constructor_registers_expected_logged_middleware_scopes(): void
    {
        $controller = new TripController(
            m::mock(Request::class),
            m::mock(TripsManager::class),
            m::mock(TripSearchRepository::class),
        );

        $middlewares = $controller->getMiddleware();

        $logged = collect($middlewares)->first(function ($entry) {
            return (is_array($entry) ? ($entry['middleware'] ?? null) : ($entry->middleware ?? null)) === 'logged';
        });
        $loggedOptional = collect($middlewares)->first(function ($entry) {
            return (is_array($entry) ? ($entry['middleware'] ?? null) : ($entry->middleware ?? null)) === 'logged.optional';
        });

        $this->assertNotNull($logged);
        $this->assertNotNull($loggedOptional);

        $loggedOptions = is_array($logged) ? ($logged['options'] ?? []) : ($logged->options ?? []);
        $loggedOptionalOptions = is_array($loggedOptional) ? ($loggedOptional['options'] ?? []) : ($loggedOptional->options ?? []);

        $this->assertSame(['search'], $loggedOptions['except'] ?? []);
        $this->assertSame(['search'], $loggedOptionalOptions['only'] ?? []);
    }

    public function test_create()
    {
        $u1 = \STS\Models\User::factory()->create(['identity_validated' => true]);
        $trip = \STS\Models\Trip::factory()->create();
        $this->actingAs($u1, 'api');

        $this->tripsLogic->shouldReceive('create')->once()->andReturn($trip);

        $response = $this->call('POST', 'api/trips/');
        $this->assertTrue($response->status() == 200);
        $response->assertJsonStructure(['data' => ['id']]);
    }

    public function test_create_returns_translated_message_when_routing_service_is_unavailable(): void
    {
        $user = User::factory()->create(['identity_validated' => true]);
        $this->actingAs($user, 'api');

        $this->tripsLogic->shouldReceive('create')->once()->andReturn(null);
        $this->tripsLogic->shouldReceive('getErrors')->atLeast()->once()->andReturn([
            'error' => ['routing_service_unavailable'],
        ]);

        $this->postJson('api/trips', [])
            ->assertUnprocessable()
            ->assertJsonPath('message', trans('errors.routing_service_unavailable'));
    }

    public function test_update()
    {
        $u1 = \STS\Models\User::factory()->create();
        $trip = \STS\Models\Trip::factory()->create();
        $this->actingAs($u1, 'api');

        $this->tripsLogic->shouldReceive('update')->once()->andReturn($trip);

        $response = $this->call('PUT', 'api/trips/'.$trip->id);
        $this->assertTrue($response->status() == 200);
        $response->assertJsonStructure(['data' => ['id']]);
    }

    public function test_update_returns_translated_message_when_routing_service_is_unavailable(): void
    {
        $user = User::factory()->create();
        $trip = Trip::factory()->create(['user_id' => $user->id]);
        $this->actingAs($user, 'api');

        $this->tripsLogic->shouldReceive('update')->once()->andReturn(null);
        $this->tripsLogic->shouldReceive('getErrors')->atLeast()->once()->andReturn([
            'error' => ['routing_service_unavailable'],
        ]);

        $this->putJson('api/trips/'.$trip->id, [])
            ->assertUnprocessable()
            ->assertJsonPath('message', trans('errors.routing_service_unavailable'));
    }

    public function test_delete()
    {
        $u1 = \STS\Models\User::factory()->create();
        $trip = \STS\Models\Trip::factory()->create();
        $this->actingAs($u1, 'api');

        $this->tripsLogic->shouldReceive('delete')->once()->andReturn(true);

        $response = $this->call('DELETE', 'api/trips/'.$trip->id);
        $this->assertTrue($response->status() == 200);
        $response->assertExactJson(['data' => 'ok']);
    }

    public function test_change_trip_seats_returns_trip_payload_on_success(): void
    {
        $user = User::factory()->create();
        $trip = Trip::factory()->create(['user_id' => $user->id]);
        $this->actingAs($user, 'api');

        $this->tripsLogic->shouldReceive('changeTripSeats')
            ->once()
            ->with($user, $trip->id, 1)
            ->andReturn($trip);

        $this->postJson('api/trips/'.$trip->id.'/changeSeats', ['increment' => 1])
            ->assertOk()
            ->assertJsonStructure(['data' => ['id']]);
    }

    public function test_change_trip_seats_returns_unprocessable_when_logic_fails(): void
    {
        $user = User::factory()->create();
        $trip = Trip::factory()->create(['user_id' => $user->id]);
        $this->actingAs($user, 'api');

        $this->tripsLogic->shouldReceive('changeTripSeats')->once()->andReturn(null);
        $this->tripsLogic->shouldReceive('getErrors')->andReturn(['error' => ['seats']]);

        $this->postJson('api/trips/'.$trip->id.'/changeSeats', ['increment' => 1])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Could not update trip.')
            ->assertJsonPath('errors.error.0', 'seats');
    }

    public function test_show()
    {
        $u1 = \STS\Models\User::factory()->create();
        $trip = \STS\Models\Trip::factory()->create();
        $this->actingAs($u1, 'api');

        $this->tripsLogic->shouldReceive('show')->once()->andReturn($trip);

        $response = $this->call('GET', 'api/trips/'.$trip->id);
        $this->assertTrue($response->status() == 200);

        $response = $this->parseJson($response);
        $this->assertTrue($trip->id == $response->data->id);
    }

    public function test_show_returns_legacy_placeholder_for_old_cordova_without_hitting_trips_logic(): void
    {
        $user = User::factory()->create();
        $trip = Trip::factory()->create(['user_id' => $user->id]);
        $this->actingAs($user, 'api');

        $this->tripsLogic->shouldReceive('show')->never();

        $this->withOldCordovaUserAgent(function () use ($trip) {
            $this->getJson('api/trips/'.$trip->id)
                ->assertOk()
                ->assertJsonPath('data.id', 0)
                ->assertJsonPath('data.from_town', 'ACTUALIZA TU APP');
        });
    }

    public function test_index()
    {
        $u1 = \STS\Models\User::factory()->create();
        $trip = \STS\Models\Trip::factory()->create();
        $this->actingAs($u1, 'api');

        $this->tripsLogic->shouldReceive('search')->once()->andReturn(Trip::paginate(10));

        $response = $this->call('GET', 'api/trips/');
        $this->assertTrue($response->status() == 200);
    }

    public function test_index_without_login()
    {
        $u1 = \STS\Models\User::factory()->create();
        $trip = \STS\Models\Trip::factory()->create();
        // $this->actingAs($u1, 'api');
        $this->tripsLogic->shouldReceive('search')->once()->andReturn(Trip::paginate(10));

        $response = $this->call('GET', 'api/trips/');

        $this->assertTrue($response->status() == 200);
    }

    public function test_my_trips()
    {
        $u1 = \STS\Models\User::factory()->create();
        $trip = \STS\Models\Trip::factory()->create();
        $this->actingAs($u1, 'api');

        $this->tripsLogic->shouldReceive('getTrips')->once()->andReturn(Trip::all());

        $response = $this->call('GET', 'api/users/my-trips/');
        $this->assertTrue($response->status() == 200);
    }

    public function test_search_merges_default_page_size_before_calling_trips_logic(): void
    {
        $this->tripsLogic->shouldReceive('search')
            ->once()
            ->with(m::any(), m::on(fn ($data) => isset($data['page_size']) && (int) $data['page_size'] === 20))
            ->andReturn(Trip::paginate(10));

        $this->getJson('api/trips')
            ->assertOk()
            ->assertJsonStructure(['data', 'meta']);
    }

    public function test_search_preserves_explicit_page_size_in_logic_payload(): void
    {
        $this->tripsLogic->shouldReceive('search')
            ->once()
            ->with(m::any(), m::on(fn ($data) => isset($data['page_size']) && (int) $data['page_size'] === 12))
            ->andReturn(Trip::paginate(12));

        $this->getJson('api/trips?page_size=12')
            ->assertOk()
            ->assertJsonPath('meta.pagination.per_page', 12);
    }

    public function test_search_invokes_trip_search_tracking_when_origin_id_is_present(): void
    {
        $this->instance(TripSearchRepository::class, m::mock(TripSearchRepository::class, function ($m) {
            $m->shouldReceive('trackSearch')->once();
        }));

        $this->tripsLogic->shouldReceive('search')->once()->andReturn(Trip::paginate(10));

        $this->getJson('api/trips?origin_id=42')
            ->assertOk();
    }

    public function test_search_tracking_passes_web_platform_zero_and_passenger_false_when_flags_omitted(): void
    {
        $this->instance(TripSearchRepository::class, m::mock(TripSearchRepository::class, function ($m) {
            $m->shouldReceive('trackSearch')
                ->once()
                ->with(
                    null,
                    42,
                    null,
                    m::any(),
                    0,
                    null,
                    false
                );
        }));

        $this->tripsLogic->shouldReceive('search')->once()->andReturn(Trip::paginate(10));

        $this->getJson('api/trips?origin_id=42')
            ->assertOk();
    }

    public function test_search_returns_exact_legacy_meta_pagination_for_old_cordova_clients(): void
    {
        $this->withOldCordovaUserAgent(function () {
            $this->getJson('api/trips')
                ->assertOk()
                ->assertJsonPath('meta.pagination.total', 1)
                ->assertJsonPath('meta.pagination.count', 1)
                ->assertJsonPath('meta.pagination.per_page', 20)
                ->assertJsonPath('meta.pagination.current_page', 1)
                ->assertJsonPath('meta.pagination.total_pages', 1)
                ->assertJsonPath('meta.pagination.links.previous', null)
                ->assertJsonPath('meta.pagination.links.next', null);
        });
    }

    public function test_search_survives_tracking_failure_and_logs_error(): void
    {
        Log::spy();

        $this->instance(TripSearchRepository::class, m::mock(TripSearchRepository::class, function ($m) {
            $m->shouldReceive('trackSearch')->once()->andThrow(new \RuntimeException('tracking failed'));
        }));

        $this->tripsLogic->shouldReceive('search')->once()->andReturn(Trip::paginate(10));

        $this->getJson('api/trips?destination_id=7')
            ->assertOk();

        Log::shouldHaveReceived('error')->once()->with('Error tracking trip search: tracking failed');
    }

    public function test_get_trips_defaults_as_driver_true_and_ignores_user_id_for_non_admin(): void
    {
        $user = User::factory()->create(['is_admin' => false]);
        $other = User::factory()->create();
        $this->actingAs($user, 'api');

        $this->tripsLogic->shouldReceive('getTrips')
            ->once()
            ->with($user, $user->id, true)
            ->andReturn(collect([]));

        $this->getJson('api/users/my-trips?user_id='.$other->id)
            ->assertOk()
            ->assertJsonStructure(['data']);
    }

    public function test_get_trips_admin_may_pass_explicit_user_id_target(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $member = User::factory()->create();
        $this->actingAs($admin, 'api');

        $this->tripsLogic->shouldReceive('getTrips')
            ->once()
            ->with($admin, $member->id, true)
            ->andReturn(collect([]));

        $this->getJson('api/users/my-trips?user_id='.$member->id)
            ->assertOk();
    }

    public function test_get_trips_honors_as_driver_false_query(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'api');

        $this->tripsLogic->shouldReceive('getTrips')
            ->once()
            ->with($user, $user->id, false)
            ->andReturn(collect([]));

        $this->getJson('api/users/my-trips?as_driver=0')
            ->assertOk();
    }

    public function test_get_old_trips_forwards_explicit_user_id_when_present(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $this->actingAs($user, 'api');

        $this->tripsLogic->shouldReceive('getOldTrips')
            ->once()
            ->with($user, $other->id, true)
            ->andReturn(collect([]));

        $this->getJson('api/users/my-old-trips?user_id='.$other->id)
            ->assertOk()
            ->assertJsonStructure(['data']);
    }

    public function test_get_old_trips_defaults_as_driver_true_when_omitted(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'api');

        $this->tripsLogic->shouldReceive('getOldTrips')
            ->once()
            ->with($user, $user->id, true)
            ->andReturn(collect([]));

        $this->getJson('api/users/my-old-trips')
            ->assertOk();
    }

    public function test_price_endpoint_returns_trips_manager_payload(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'api');

        $expected = ['currency' => 'ARS', 'total_cents' => 500];
        $this->tripsLogic->shouldReceive('price')
            ->once()
            ->with('O', 'D', '120')
            ->andReturn($expected);

        $this->postJson('api/trips/price', ['from' => 'O', 'to' => 'D', 'distance' => '120'])
            ->assertOk()
            ->assertExactJson($expected);
    }

    public function test_trip_info_endpoint_returns_trips_manager_payload(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'api');

        $points = [['lat' => -34.0, 'lng' => -58.0]];
        $expected = ['distance_m' => 1000];
        $this->tripsLogic->shouldReceive('getTripInfo')->once()->with($points)->andReturn($expected);

        $this->postJson('api/trips/trip-info', ['points' => $points])
            ->assertOk()
            ->assertExactJson($expected);
    }

    public function test_sellado_viaje_returns_success_envelope(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'api');

        $payload = ['enabled' => false, 'reason' => 'test'];
        $this->tripsLogic->shouldReceive('selladoViaje')->once()->with($user)->andReturn($payload);

        $this->getJson('api/users/sellado-viaje')
            ->assertOk()
            ->assertExactJson([
                'success' => true,
                'data' => $payload,
            ]);
    }

    public function test_change_visibility_returns_trip_payload_on_success(): void
    {
        $user = User::factory()->create();
        $trip = Trip::factory()->create(['user_id' => $user->id]);
        $this->actingAs($user, 'api');

        $this->tripsLogic->shouldReceive('changeVisibility')
            ->once()
            ->with($user, $trip->id)
            ->andReturn($trip);

        $this->postJson('api/trips/'.$trip->id.'/change-visibility', [])
            ->assertOk()
            ->assertJsonStructure(['data' => ['id']]);
    }

    public function test_change_visibility_returns_unprocessable_when_logic_fails(): void
    {
        $user = User::factory()->create();
        $trip = Trip::factory()->create(['user_id' => $user->id]);
        $this->actingAs($user, 'api');

        $this->tripsLogic->shouldReceive('changeVisibility')->once()->andReturn(null);
        $this->tripsLogic->shouldReceive('getErrors')->andReturn(['error' => ['visibility']]);

        $this->postJson('api/trips/'.$trip->id.'/change-visibility', [])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Could not update trip.')
            ->assertJsonPath('errors.error.0', 'visibility');
    }

    public function test_create_returns_default_message_when_create_fails_and_errors_are_null(): void
    {
        $user = User::factory()->create(['identity_validated' => true]);
        $this->actingAs($user, 'api');

        $this->tripsLogic->shouldReceive('create')->once()->andReturn(null);
        $this->tripsLogic->shouldReceive('getErrors')->atLeast()->once()->andReturn(null);

        $this->postJson('api/trips', [])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Could not create new trip.');
    }

    public function test_errors_contain_code_casts_dynamic_stdclass_errors_to_array(): void
    {
        $controller = new TripController(
            Request::create('/'),
            $this->tripsLogic,
            m::mock(TripSearchRepository::class),
        );

        $payload = new \stdClass;
        $payload->error = 'routing_service_unavailable';

        $method = new ReflectionMethod(TripController::class, 'errorsContainCode');
        $method->setAccessible(true);

        $this->assertTrue($method->invoke($controller, $payload, 'routing_service_unavailable'));
    }

    public function test_errors_contain_code_returns_false_when_error_key_is_explicitly_null(): void
    {
        $controller = new TripController(
            Request::create('/'),
            $this->tripsLogic,
            m::mock(TripSearchRepository::class),
        );

        $method = new ReflectionMethod(TripController::class, 'errorsContainCode');
        $method->setAccessible(true);

        $this->assertFalse($method->invoke($controller, ['error' => null], 'routing_service_unavailable'));
    }
}
