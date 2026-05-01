<?php

namespace Tests\Unit\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use STS\Services\MapboxDirectionsRouteService;
use Tests\TestCase;

class MapboxDirectionsRouteServiceTest extends TestCase
{
    public function test_is_enabled_requires_non_empty_mapbox_token(): void
    {
        config(['carpoolear.mapbox_access_token' => '']);
        $service = new MapboxDirectionsRouteService;
        $this->assertFalse($service->isEnabled());

        config(['carpoolear.mapbox_access_token' => 'pk.test-token']);
        $this->assertTrue($service->isEnabled());
    }

    public function test_driving_distance_returns_null_when_disabled(): void
    {
        config(['carpoolear.mapbox_access_token' => '']);
        $service = new MapboxDirectionsRouteService;

        $this->assertNull($service->drivingDistanceAndDuration([
            ['lat' => 0, 'lng' => 0],
            ['lat' => 1, 'lng' => 1],
        ]));
    }

    public function test_driving_distance_returns_null_for_zero_or_one_point_when_enabled(): void
    {
        config(['carpoolear.mapbox_access_token' => 'pk.x']);
        $service = new MapboxDirectionsRouteService;

        $this->assertNull($service->drivingDistanceAndDuration([]));
        $this->assertNull($service->drivingDistanceAndDuration([
            ['lat' => 0, 'lng' => 0],
        ]));
    }

    public function test_driving_distance_logs_and_returns_null_when_more_than_twenty_five_points(): void
    {
        Log::spy();
        config(['carpoolear.mapbox_access_token' => 'pk.x']);
        $service = new MapboxDirectionsRouteService;

        $points = [];
        for ($i = 0; $i < 26; $i++) {
            $points[] = ['lat' => $i * 0.01, 'lng' => $i * 0.01];
        }

        $this->assertNull($service->drivingDistanceAndDuration($points));

        Log::shouldHaveReceived('warning')->once()->withArgs(function (string $message, array $context): bool {
            return str_contains($message, 'too many coordinates')
                && ($context['count'] ?? 0) === 26;
        });
    }

    public function test_driving_distance_returns_rounded_meters_and_seconds_on_successful_response(): void
    {
        config(['carpoolear.mapbox_access_token' => 'pk.x']);
        Http::fake([
            'https://api.mapbox.com/*' => Http::response([
                'routes' => [
                    [
                        'distance' => 1000.4,
                        'duration' => 120.6,
                    ],
                ],
            ], 200),
        ]);

        $service = new MapboxDirectionsRouteService;
        $result = $service->drivingDistanceAndDuration([
            ['lat' => -34.6, 'lng' => -58.4],
            ['lat' => -34.7, 'lng' => -58.5],
        ]);

        $this->assertSame(['distance' => 1000, 'duration' => 121], $result);
    }

    public function test_successful_request_uses_lng_lat_path_and_query_parameters(): void
    {
        config(['carpoolear.mapbox_access_token' => 'pk.abc']);
        Http::fake([
            '*' => Http::response([
                'routes' => [['distance' => 1.0, 'duration' => 2.0]],
            ], 200),
        ]);

        $service = new MapboxDirectionsRouteService;
        $service->drivingDistanceAndDuration([
            ['lat' => '-34.6', 'lng' => '-58.4'],
            ['lat' => 10, 'lng' => 20],
        ]);

        Http::assertSent(function (\Illuminate\Http\Client\Request $request): bool {
            $url = $request->url();

            return str_contains($url, '/directions/v5/mapbox/driving/-58.4,-34.6;20,10.json')
                && str_contains($url, 'overview=false')
                && str_contains($url, 'alternatives=false')
                && str_contains($url, 'access_token=pk.abc');
        });
    }

    public function test_http_error_logs_status_and_bounded_body_preview_then_returns_null(): void
    {
        Log::spy();
        config(['carpoolear.mapbox_access_token' => 'pk.x']);
        $longBody = str_repeat('x', 400);
        Http::fake([
            '*' => Http::response($longBody, 502),
        ]);

        $service = new MapboxDirectionsRouteService;
        $this->assertNull($service->drivingDistanceAndDuration([
            ['lat' => 0, 'lng' => 0],
            ['lat' => 1, 'lng' => 1],
        ]));

        Log::shouldHaveReceived('warning')->once()->withArgs(function (string $message, array $context): bool {
            if (! str_contains($message, 'HTTP error')) {
                return false;
            }
            if (($context['status'] ?? null) !== 502) {
                return false;
            }
            $preview = $context['body_preview'] ?? '';

            return strlen($preview) <= 300;
        });
    }

    public function test_connection_exception_logs_message_and_returns_null(): void
    {
        Log::spy();
        config(['carpoolear.mapbox_access_token' => 'pk.x']);
        Http::fake(function (): never {
            throw new ConnectionException('network down');
        });

        $service = new MapboxDirectionsRouteService;
        $this->assertNull($service->drivingDistanceAndDuration([
            ['lat' => 0, 'lng' => 0],
            ['lat' => 1, 'lng' => 1],
        ]));

        Log::shouldHaveReceived('warning')->once()->withArgs(function (string $message, array $context): bool {
            return str_contains($message, 'request exception')
                && ($context['message'] ?? '') === 'network down';
        });
    }

    public function test_non_json_success_body_returns_null(): void
    {
        config(['carpoolear.mapbox_access_token' => 'pk.x']);
        Http::fake([
            '*' => Http::response('not json', 200, ['Content-Type' => 'text/plain']),
        ]);

        $service = new MapboxDirectionsRouteService;
        $this->assertNull($service->drivingDistanceAndDuration([
            ['lat' => 0, 'lng' => 0],
            ['lat' => 1, 'lng' => 1],
        ]));
    }

    public function test_empty_routes_logs_no_route_and_returns_null(): void
    {
        Log::spy();
        config(['carpoolear.mapbox_access_token' => 'pk.x']);
        Http::fake([
            '*' => Http::response([
                'code' => 'NoRoute',
                'message' => 'Impossible route',
                'routes' => [],
            ], 200),
        ]);

        $service = new MapboxDirectionsRouteService;
        $this->assertNull($service->drivingDistanceAndDuration([
            ['lat' => 0, 'lng' => 0],
            ['lat' => 1, 'lng' => 1],
        ]));

        Log::shouldHaveReceived('info')->once()->withArgs(function (string $message, array $context): bool {
            return str_contains($message, 'no route')
                && ($context['code'] ?? null) === 'NoRoute'
                && ($context['message'] ?? null) === 'Impossible route';
        });
    }

    public function test_route_missing_distance_or_duration_returns_null(): void
    {
        config(['carpoolear.mapbox_access_token' => 'pk.x']);
        Http::fake([
            '*' => Http::response([
                'routes' => [
                    ['duration' => 10],
                ],
            ], 200),
        ]);

        $service = new MapboxDirectionsRouteService;
        $this->assertNull($service->drivingDistanceAndDuration([
            ['lat' => 0, 'lng' => 0],
            ['lat' => 1, 'lng' => 1],
        ]));
    }

    public function test_success_response_requires_first_route_index_zero(): void
    {
        config(['carpoolear.mapbox_access_token' => 'pk.x']);
        Http::fake([
            '*' => Http::response([
                'routes' => [
                    ['distance' => 100, 'duration' => 10],
                    ['distance' => 999, 'duration' => 99],
                ],
            ], 200),
        ]);

        $service = new MapboxDirectionsRouteService;
        $result = $service->drivingDistanceAndDuration([
            ['lat' => 0, 'lng' => 0],
            ['lat' => 1, 'lng' => 1],
        ]);

        $this->assertSame(['distance' => 100, 'duration' => 10], $result);
    }
}
