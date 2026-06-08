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

    public function test_rejects_path_with_length_4097_characters(): void
    {
        $path = 'driving/'.str_repeat('0', 4089);

        $this->assertSame(4097, strlen($path));

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

        $path = 'driving/-32.9,-60.7;-34.6,-58.4';
        $preview = substr($path, 0, 96);

        $this->getJson('api/osrm/route/v1/'.$path)
            ->assertOk()
            ->assertExactJson([
                'code' => 'NoRoute',
                'message' => 'Routing service unavailable',
                'routes' => [],
                'waypoints' => [],
            ])
            ->assertHeader('X-OSRM-Proxy-Cache', 'MISS')
            ->assertHeader('X-OSRM-Proxy-Error', 'upstream_failed');

        Log::shouldHaveReceived('warning')->withArgs(function (...$args) use ($preview): bool {
            if (count($args) < 1 || $args[0] !== '[osrm_proxy] all upstream attempts failed') {
                return false;
            }
            $ctx = $args[1] ?? [];

            return is_array($ctx) && ($ctx['path_preview'] ?? null) === $preview;
        });
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

    public function test_cache_hit_logs_debug_with_path_preview_truncated_to_96_chars(): void
    {
        config([
            'carpoolear.osrm_router_base_url' => 'https://osrm-preview.test',
            'carpoolear.osrm_router_fallback_base_url' => null,
        ]);

        $tail = str_repeat('z', 120);
        $path = 'driving/-1.0,-1.0;-2.0,-2.0_'.$tail;
        $this->assertGreaterThan(96, strlen($path));

        Http::fake([
            'https://osrm-preview.test/*' => Http::response([
                'code' => 'Ok',
                'routes' => [['distance' => 1, 'duration' => 1]],
                'waypoints' => [],
            ], 200),
        ]);

        $uri = 'api/osrm/route/v1/'.$path;

        $this->getJson($uri)->assertOk()->assertHeader('X-OSRM-Proxy-Cache', 'MISS');
        $this->getJson($uri)->assertOk()->assertHeader('X-OSRM-Proxy-Cache', 'HIT');

        $expectedPreview = substr($path, 0, 96);

        Log::shouldHaveReceived('debug')->withArgs(function (...$args) use ($expectedPreview): bool {
            if (count($args) < 1 || $args[0] !== '[osrm_proxy] cache STORE') {
                return false;
            }
            $ctx = $args[1] ?? [];

            return is_array($ctx)
                && ($ctx['path_preview'] ?? null) === $expectedPreview
                && ($ctx['osrm_code'] ?? null) === 'Ok'
                && isset($ctx['ttl_seconds']);
        });

        Log::shouldHaveReceived('debug')->withArgs(function (...$args) use ($expectedPreview): bool {
            if (count($args) < 1 || $args[0] !== '[osrm_proxy] cache HIT') {
                return false;
            }
            $ctx = $args[1] ?? [];

            return is_array($ctx)
                && ($ctx['path_preview'] ?? null) === $expectedPreview;
        });
    }

    public function test_cache_get_uses_key_prefixed_with_osrm_proxy_v1_and_sha256_of_path_and_query(): void
    {
        Cache::spy();
        config([
            'carpoolear.osrm_router_base_url' => 'https://osrm-key.test',
            'carpoolear.osrm_router_fallback_base_url' => null,
        ]);

        Http::fake([
            'https://osrm-key.test/*' => Http::response([
                'code' => 'Ok',
                'routes' => [['distance' => 2, 'duration' => 2]],
                'waypoints' => [],
            ], 200),
        ]);

        $path = 'driving/-11.1,-11.1;-22.2,-22.2';
        $query = 'overview=simplified';
        $expectedKey = 'osrm_proxy:v1:'.hash('sha256', $path.'|'.$query);

        $this->getJson('api/osrm/route/v1/'.$path.'?'.$query)->assertOk();

        Cache::shouldHaveReceived('get')->with($expectedKey);
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

    public function test_upstream_non_success_http_logs_warning_with_base_and_status(): void
    {
        config([
            'carpoolear.osrm_router_base_url' => 'https://primary-502.test',
            'carpoolear.osrm_router_fallback_base_url' => null,
        ]);

        Http::fake([
            'https://primary-502.test/*' => Http::response('bad', 502),
        ]);

        $this->getJson('api/osrm/route/v1/driving/-20.0,-20.0;-21.0,-21.0')
            ->assertOk()
            ->assertJsonPath('code', 'NoRoute');

        Log::shouldHaveReceived('warning')->withArgs(function (...$args): bool {
            if (count($args) < 1 || $args[0] !== '[osrm_proxy] upstream HTTP not successful') {
                return false;
            }
            $ctx = $args[1] ?? [];

            return is_array($ctx)
                && str_contains((string) ($ctx['base'] ?? ''), 'primary-502.test')
                && ($ctx['http_status'] ?? null) === 502;
        });
    }

    public function test_upstream_ok_returns_osrm_payload(): void
    {
        config([
            'carpoolear.osrm_router_base_url' => 'https://osrm-info.test',
            'carpoolear.osrm_router_fallback_base_url' => null,
        ]);

        Http::fake([
            'https://osrm-info.test/*' => Http::response([
                'code' => 'Ok',
                'routes' => [['distance' => 9, 'duration' => 9]],
                'waypoints' => [],
            ], 200),
        ]);

        $this->getJson('api/osrm/route/v1/driving/-30.0,-30.0;-31.0,-31.0')
            ->assertOk()
            ->assertJsonPath('code', 'Ok');

    }

    public function test_blank_primary_base_is_skipped_and_fallback_is_used(): void
    {
        config([
            'carpoolear.osrm_router_base_url' => '',
            'carpoolear.osrm_router_fallback_base_url' => 'https://only-fallback.test',
        ]);

        Http::fake([
            'https://only-fallback.test/*' => Http::response([
                'code' => 'Ok',
                'routes' => [['distance' => 42, 'duration' => 4]],
                'waypoints' => [],
            ], 200),
        ]);

        $this->getJson('api/osrm/route/v1/driving/-40.0,-40.0;-41.0,-41.0')
            ->assertOk()
            ->assertJsonPath('code', 'Ok')
            ->assertJsonPath('routes.0.distance', 42);

        Http::assertSent(function (\Illuminate\Http\Client\Request $request): bool {
            return str_contains($request->url(), 'only-fallback.test');
        });
    }

    public function test_get_request_appends_query_string_to_upstream_url(): void
    {
        config([
            'carpoolear.osrm_router_base_url' => 'https://osrm-query-upstream.test',
            'carpoolear.osrm_router_fallback_base_url' => null,
        ]);

        Http::fake([
            'https://osrm-query-upstream.test/*' => Http::response([
                'code' => 'Ok',
                'routes' => [],
                'waypoints' => [],
            ], 200),
        ]);

        $this->get('api/osrm/route/v1/driving/-50.0,-50.0;-51.0,-51.0?steps=true&geometries=geojson')
            ->assertOk();

        $recorded = Http::recorded();
        $this->assertCount(1, $recorded);
        $url = $recorded[0][0]->url();
        $this->assertStringContainsString('/route/v1/driving/-50.0,-50.0;-51.0,-51.0', $url);
        $this->assertStringContainsString('steps=true', $url);
        $this->assertStringContainsString('geometries=geojson', $url);
    }

    public function test_osrm_error_response_uses_error_cache_ttl_in_put_expiry(): void
    {
        config([
            'carpoolear.osrm_router_base_url' => 'https://osrm-err-ttl.test',
            'carpoolear.osrm_router_fallback_base_url' => null,
            'carpoolear.osrm_proxy_cache_ttl_error_seconds' => 120,
        ]);

        Http::fake([
            '*' => Http::response([
                'code' => 'NoSegment',
                'message' => 'no route',
                'routes' => [],
                'waypoints' => [],
            ], 200),
        ]);

        Cache::spy();

        $this->getJson('api/osrm/route/v1/driving/-60.0,-60.0;-61.0,-61.0')
            ->assertOk()
            ->assertJsonPath('code', 'NoSegment');

        Cache::shouldHaveReceived('put')->with(
            Mockery::type('string'),
            Mockery::type('array'),
            Mockery::on(function ($expiry): bool {
                if (! $expiry instanceof \DateTimeInterface) {
                    return false;
                }
                $at = \Carbon\Carbon::parse($expiry);

                return $at->between(now()->addSeconds(115), now()->addSeconds(130), true);
            })
        );
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
