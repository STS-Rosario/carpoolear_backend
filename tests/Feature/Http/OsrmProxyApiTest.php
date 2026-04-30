<?php

namespace Tests\Feature\Http;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Mockery;
use STS\Http\Controllers\Api\v1\OsrmProxyController;
use Tests\TestCase;

class OsrmProxyApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    protected function tearDown(): void
    {
        Http::fake();
        parent::tearDown();
    }

    public function test_rejects_path_longer_than_4096_characters(): void
    {
        $path = 'driving/'.str_repeat('0', 4090);

        $this->assertGreaterThan(4096, strlen($path));

        $this->getJson('api/osrm/route/v1/'.$path)
            ->assertStatus(400)
            ->assertExactJson([
                'code' => 'InvalidUrl',
                'message' => 'Path too long',
            ]);
    }

    public function test_accepts_path_with_exactly_4096_characters(): void
    {
        $path = 'driving/'.str_repeat('0', 4088);
        $this->assertSame(4096, strlen($path));

        config([
            'carpoolear.osrm_router_base_url' => 'https://osrm-proxy-test.example',
            'carpoolear.osrm_router_fallback_base_url' => null,
        ]);

        Http::fake([
            'https://osrm-proxy-test.example/*' => Http::response([
                'code' => 'Ok',
                'routes' => [['distance' => 10, 'duration' => 1]],
                'waypoints' => [],
            ], 200),
        ]);

        $this->getJson('api/osrm/route/v1/'.$path)
            ->assertOk()
            ->assertJsonPath('code', 'Ok');
    }

    public function test_returns_no_route_envelope_when_upstream_unreachable(): void
    {
        config([
            'carpoolear.osrm_router_base_url' => 'https://127.0.0.1:9',
            'carpoolear.osrm_router_fallback_base_url' => null,
        ]);

        Http::fake([
            '*' => Http::response(['code' => 'Error'], 503),
        ]);

        $this->getJson('api/osrm/route/v1/driving/-32.9,-60.7;-34.6,-58.4')
            ->assertOk()
            ->assertExactJson([
                'code' => 'NoRoute',
                'message' => 'Routing service unavailable',
                'routes' => [],
                'waypoints' => [],
            ])
            ->assertHeader('X-OSRM-Proxy-Cache', 'MISS')
            ->assertHeader('X-OSRM-Proxy-Error', 'upstream_failed');
    }

    public function test_returns_upstream_json_on_miss_and_serves_second_identical_request_from_cache(): void
    {
        config([
            'carpoolear.osrm_router_base_url' => 'https://osrm-proxy-test.example',
            'carpoolear.osrm_router_fallback_base_url' => null,
        ]);

        $payload = [
            'code' => 'Ok',
            'routes' => [['distance' => 12_345, 'duration' => 600]],
            'waypoints' => [],
        ];

        Http::fake([
            'https://osrm-proxy-test.example/*' => Http::response($payload, 200),
        ]);

        $uri = 'api/osrm/route/v1/driving/-1.0,-1.0;-2.0,-2.0';

        $this->getJson($uri)
            ->assertOk()
            ->assertHeader('X-OSRM-Proxy-Cache', 'MISS')
            ->assertJsonPath('code', 'Ok')
            ->assertJsonPath('routes.0.distance', 12_345);

        $this->getJson($uri)
            ->assertOk()
            ->assertHeader('X-OSRM-Proxy-Cache', 'HIT')
            ->assertJsonPath('code', 'Ok')
            ->assertJsonPath('routes.0.distance', 12_345);

        Http::assertSentCount(1);
    }

    public function test_query_string_participates_in_cache_key(): void
    {
        config([
            'carpoolear.osrm_router_base_url' => 'https://osrm-proxy-test.example',
            'carpoolear.osrm_router_fallback_base_url' => null,
        ]);

        Http::fake([
            'https://osrm-proxy-test.example/*' => Http::response([
                'code' => 'Ok',
                'routes' => [['distance' => 111, 'duration' => 11]],
                'waypoints' => [],
            ], 200),
        ]);

        $base = 'api/osrm/route/v1/driving/-1.1,-1.1;-2.2,-2.2';
        $this->getJson($base.'?overview=false')->assertOk()->assertHeader('X-OSRM-Proxy-Cache', 'MISS');
        $this->getJson($base.'?overview=full')->assertOk()->assertHeader('X-OSRM-Proxy-Cache', 'MISS');
        $this->getJson($base.'?overview=false')->assertOk()->assertHeader('X-OSRM-Proxy-Cache', 'HIT');

        Http::assertSentCount(2);
    }

    public function test_retries_fallback_base_when_primary_returns_unsuccessful_http(): void
    {
        config([
            'carpoolear.osrm_router_base_url' => 'https://primary-osrm.test',
            'carpoolear.osrm_router_fallback_base_url' => 'https://fallback-osrm.test',
        ]);

        $okPayload = [
            'code' => 'Ok',
            'routes' => [['distance' => 100, 'duration' => 10]],
            'waypoints' => [],
        ];

        Http::fake([
            'https://primary-osrm.test/*' => Http::response('bad gateway', 502),
            'https://fallback-osrm.test/*' => Http::response($okPayload, 200),
        ]);

        $this->getJson('api/osrm/route/v1/driving/-3.0,-3.0;-4.0,-4.0')
            ->assertOk()
            ->assertHeader('X-OSRM-Proxy-Cache', 'MISS')
            ->assertJsonPath('code', 'Ok')
            ->assertJsonPath('routes.0.distance', 100);

        Http::assertSentCount(2);
    }

    public function test_treats_successful_http_without_osrm_code_key_as_upstream_failure(): void
    {
        config([
            'carpoolear.osrm_router_base_url' => 'https://weird-json.test',
            'carpoolear.osrm_router_fallback_base_url' => null,
        ]);

        Http::fake([
            '*' => Http::response(['not_osrm' => true], 200),
        ]);

        $this->getJson('api/osrm/route/v1/driving/-5.0,-5.0;-6.0,-6.0')
            ->assertOk()
            ->assertJsonPath('code', 'NoRoute')
            ->assertJsonPath('message', 'Routing service unavailable')
            ->assertHeader('X-OSRM-Proxy-Cache', 'MISS')
            ->assertHeader('X-OSRM-Proxy-Error', 'upstream_failed');
    }

    public function test_route_rejects_non_driving_profile_when_invoked_directly(): void
    {
        $controller = app(OsrmProxyController::class);
        $response = $controller->route(Request::create('/', 'GET'), 'walking/-1.0,-1.0;-2.0,-2.0');

        $this->assertSame(400, $response->getStatusCode());
        $this->assertSame([
            'code' => 'InvalidUrl',
            'message' => 'Only driving profile is supported',
        ], $response->getData(true));
    }

    public function test_cache_store_uses_minimum_sixty_second_ttl_when_success_ttl_config_below_sixty(): void
    {
        config([
            'carpoolear.osrm_router_base_url' => 'https://osrm-ttl-min.test',
            'carpoolear.osrm_router_fallback_base_url' => null,
            'carpoolear.osrm_proxy_cache_ttl_success_seconds' => 30,
        ]);

        Http::fake([
            '*' => Http::response([
                'code' => 'Ok',
                'routes' => [['distance' => 1, 'duration' => 1]],
                'waypoints' => [],
            ], 200),
        ]);

        Cache::spy();

        $this->getJson('api/osrm/route/v1/driving/-7.0,-7.0;-8.0,-8.0')
            ->assertOk()
            ->assertHeader('X-OSRM-Proxy-Cache', 'MISS');

        Cache::shouldHaveReceived('put')->with(
            Mockery::type('string'),
            Mockery::type('array'),
            Mockery::on(function ($expiry): bool {
                if (! $expiry instanceof \DateTimeInterface) {
                    return false;
                }
                $at = \Carbon\Carbon::parse($expiry);

                return $at->between(now()->addSeconds(55), now()->addSeconds(70), true);
            })
        );
    }

    public function test_logs_upstream_exception_and_uses_fallback_base(): void
    {
        config([
            'carpoolear.osrm_router_base_url' => 'https://primary-throws.test',
            'carpoolear.osrm_router_fallback_base_url' => 'https://fallback-ok.test',
        ]);

        Http::fake(function (\Illuminate\Http\Client\Request $request) {
            if (str_contains($request->url(), 'primary-throws.test')) {
                throw new ConnectionException('simulated connection failure');
            }

            return Http::response([
                'code' => 'Ok',
                'routes' => [['distance' => 55, 'duration' => 5]],
                'waypoints' => [],
            ], 200);
        });

        Log::spy();

        $this->getJson('api/osrm/route/v1/driving/-9.0,-9.0;-10.0,-10.0')
            ->assertOk()
            ->assertJsonPath('code', 'Ok')
            ->assertJsonPath('routes.0.distance', 55);

        Log::shouldHaveReceived('warning')->withArgs(function (string $message, array $context): bool {
            return $message === '[osrm_proxy] upstream exception'
                && str_contains((string) ($context['message'] ?? ''), 'simulated connection failure')
                && str_contains((string) ($context['base'] ?? ''), 'primary-throws.test');
        });

        Http::assertSent(function (\Illuminate\Http\Client\Request $request): bool {
            return str_contains($request->url(), 'fallback-ok.test');
        });
    }
}
