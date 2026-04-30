<?php

namespace Tests\Feature\Http;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
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
}
