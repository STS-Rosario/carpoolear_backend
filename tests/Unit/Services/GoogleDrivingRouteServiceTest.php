<?php

namespace Tests\Unit\Services;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Mockery;
use ReflectionMethod;
use STS\Services\GoogleDrivingRouteService;
use Tests\TestCase;

class GoogleDrivingRouteServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_is_enabled_is_false_when_google_routes_key_missing_or_empty(): void
    {
        Config::set('carpoolear.google_routes_api_key', '');

        $this->assertFalse((new GoogleDrivingRouteService)->isEnabled());
    }

    public function test_is_enabled_is_true_when_api_key_has_non_zero_length(): void
    {
        Config::set('carpoolear.google_routes_api_key', 'any-key');

        $this->assertTrue((new GoogleDrivingRouteService)->isEnabled());
    }

    public function test_is_enabled_coerces_numeric_config_to_string_before_strlen(): void
    {
        Config::set('carpoolear.google_routes_api_key', 404);

        $this->assertTrue((new GoogleDrivingRouteService)->isEnabled());
    }

    public function test_driving_distance_returns_null_when_service_disabled(): void
    {
        Config::set('carpoolear.google_routes_api_key', '');

        $svc = new GoogleDrivingRouteService;
        $this->assertNull($svc->drivingDistanceAndDuration([
            ['lat' => 0, 'lng' => 0],
            ['lat' => 1, 'lng' => 1],
        ]));
    }

    public function test_driving_distance_returns_null_when_fewer_than_two_points(): void
    {
        Config::set('carpoolear.google_routes_api_key', 'k');

        $this->assertNull((new GoogleDrivingRouteService)->drivingDistanceAndDuration([
            ['lat' => -34.0, 'lng' => -58.0],
        ]));
    }

    public function test_driving_distance_returns_null_when_more_than_twenty_seven_points(): void
    {
        Config::set('carpoolear.google_routes_api_key', 'k');
        $points = [];
        for ($i = 0; $i < 28; $i++) {
            $points[] = ['lat' => $i * 0.01, 'lng' => $i * 0.01];
        }

        Log::shouldReceive('warning')
            ->once()
            ->with(
                '[google_routes] too many waypoints for Routes API',
                ['count' => 28]
            );

        $this->assertNull((new GoogleDrivingRouteService)->drivingDistanceAndDuration($points));
    }

    public function test_driving_distance_accepts_exactly_twenty_seven_points(): void
    {
        Config::set('carpoolear.google_routes_api_key', 'k');
        Config::set('carpoolear.google_routes_region_code', '');

        $points = [];
        for ($i = 0; $i < 27; $i++) {
            $points[] = ['lat' => $i * 0.01, 'lng' => $i * 0.02];
        }

        Http::fake([
            'https://routes.googleapis.com/*' => Http::response([
                'routes' => [[
                    'distanceMeters' => 500,
                    'duration' => '120s',
                ]],
            ], 200),
        ]);

        $out = (new GoogleDrivingRouteService)->drivingDistanceAndDuration($points);

        $this->assertSame(['distance' => 500, 'duration' => 120], $out);

        Http::assertSent(function (\Illuminate\Http\Client\Request $request): bool {
            $data = $request->data();
            if (($data['travelMode'] ?? null) !== 'DRIVE') {
                return false;
            }
            $inter = $data['intermediates'] ?? null;

            return is_array($inter) && count($inter) === 25;
        });
    }

    public function test_driving_distance_two_points_omits_intermediates_key(): void
    {
        Config::set('carpoolear.google_routes_api_key', 'k');
        Config::set('carpoolear.google_routes_region_code', '');

        Http::fake([
            'https://routes.googleapis.com/*' => Http::response([
                'routes' => [[
                    'distanceMeters' => 10,
                    'duration' => '5s',
                ]],
            ], 200),
        ]);

        $out = (new GoogleDrivingRouteService)->drivingDistanceAndDuration([
            ['lat' => '-34.5', 'lng' => '58'],
            ['lat' => -31.0, 'lng' => -64.0],
        ]);

        $this->assertSame(['distance' => 10, 'duration' => 5], $out);

        Http::assertSent(function (\Illuminate\Http\Client\Request $request): bool {
            $data = $request->data();

            return ! array_key_exists('intermediates', $data);
        });
    }

    public function test_driving_distance_includes_region_code_when_config_is_non_empty_string(): void
    {
        Config::set('carpoolear.google_routes_api_key', 'k');
        Config::set('carpoolear.google_routes_region_code', 'AR');

        Http::fake([
            'https://routes.googleapis.com/*' => Http::response([
                'routes' => [[
                    'distanceMeters' => 1,
                    'duration' => '1s',
                ]],
            ], 200),
        ]);

        (new GoogleDrivingRouteService)->drivingDistanceAndDuration([
            ['lat' => 0, 'lng' => 0],
            ['lat' => 1, 'lng' => 1],
        ]);

        Http::assertSent(fn (\Illuminate\Http\Client\Request $request) => ($request->data()['regionCode'] ?? null) === 'AR');
    }

    public function test_driving_distance_omits_region_code_when_config_not_string(): void
    {
        Config::set('carpoolear.google_routes_api_key', 'k');
        Config::set('carpoolear.google_routes_region_code', ['AR']);

        Http::fake([
            'https://routes.googleapis.com/*' => Http::response([
                'routes' => [[
                    'distanceMeters' => 2,
                    'duration' => '2s',
                ]],
            ], 200),
        ]);

        (new GoogleDrivingRouteService)->drivingDistanceAndDuration([
            ['lat' => 0, 'lng' => 0],
            ['lat' => 1, 'lng' => 1],
        ]);

        Http::assertSent(fn (\Illuminate\Http\Client\Request $request) => ! array_key_exists('regionCode', $request->data()));
    }

    public function test_driving_distance_returns_null_on_http_error_and_logs_preview(): void
    {
        Config::set('carpoolear.google_routes_api_key', 'k');
        Config::set('carpoolear.google_routes_region_code', '');

        Http::fake([
            'https://routes.googleapis.com/*' => Http::response(str_repeat('e', 400), 502),
        ]);

        Log::shouldReceive('warning')
            ->once()
            ->with(
                '[google_routes] HTTP error',
                Mockery::on(function (array $ctx): bool {
                    return ($ctx['status'] ?? null) === 502
                        && is_string($ctx['body_preview'] ?? null)
                        && strlen($ctx['body_preview'] ?? '') <= 300;
                })
            );

        $this->assertNull((new GoogleDrivingRouteService)->drivingDistanceAndDuration([
            ['lat' => 0, 'lng' => 0],
            ['lat' => 1, 'lng' => 1],
        ]));
    }

    public function test_driving_distance_returns_null_when_response_has_no_routes(): void
    {
        Config::set('carpoolear.google_routes_api_key', 'k');
        Config::set('carpoolear.google_routes_region_code', '');

        Http::fake([
            'https://routes.googleapis.com/*' => Http::response(['routes' => []], 200),
        ]);

        Log::shouldReceive('info')->once()->with('[google_routes] no routes in response');

        $this->assertNull((new GoogleDrivingRouteService)->drivingDistanceAndDuration([
            ['lat' => 0, 'lng' => 0],
            ['lat' => 1, 'lng' => 1],
        ]));
    }

    public function test_driving_distance_returns_null_when_distance_or_duration_missing(): void
    {
        Config::set('carpoolear.google_routes_api_key', 'k');
        Config::set('carpoolear.google_routes_region_code', '');

        Http::fake([
            'https://routes.googleapis.com/*' => Http::response([
                'routes' => [['duration' => '10s']],
            ], 200),
        ]);

        $this->assertNull((new GoogleDrivingRouteService)->drivingDistanceAndDuration([
            ['lat' => 0, 'lng' => 0],
            ['lat' => 1, 'lng' => 1],
        ]));
    }

    public function test_driving_distance_returns_null_when_duration_string_not_parseable(): void
    {
        Config::set('carpoolear.google_routes_api_key', 'k');
        Config::set('carpoolear.google_routes_region_code', '');

        Http::fake([
            'https://routes.googleapis.com/*' => Http::response([
                'routes' => [[
                    'distanceMeters' => 100,
                    'duration' => 'not-a-duration',
                ]],
            ], 200),
        ]);

        $this->assertNull((new GoogleDrivingRouteService)->drivingDistanceAndDuration([
            ['lat' => 0, 'lng' => 0],
            ['lat' => 1, 'lng' => 1],
        ]));
    }

    public function test_driving_distance_success_casts_distance_and_rounds_duration_seconds(): void
    {
        Config::set('carpoolear.google_routes_api_key', 'k');
        Config::set('carpoolear.google_routes_region_code', '');

        Http::fake([
            'https://routes.googleapis.com/*' => Http::response([
                'routes' => [[
                    'distanceMeters' => 999.7,
                    'duration' => '12.4s',
                ]],
            ], 200),
        ]);

        $out = (new GoogleDrivingRouteService)->drivingDistanceAndDuration([
            ['lat' => 0, 'lng' => 0],
            ['lat' => 1, 'lng' => 1],
        ]);

        $this->assertSame(['distance' => 999, 'duration' => 12], $out);
    }

    public function test_parse_duration_seconds_via_reflection(): void
    {
        $m = new ReflectionMethod(GoogleDrivingRouteService::class, 'parseDurationSeconds');
        $m->setAccessible(true);
        $svc = new GoogleDrivingRouteService;

        $this->assertNull($m->invoke($svc, ''));
        $this->assertNull($m->invoke($svc, '   '));
        $this->assertNull($m->invoke($svc, '10'));
        $this->assertNull($m->invoke($svc, 'xs'));
        $this->assertSame(7.5, $m->invoke($svc, " 7.5s \n"));
    }

    public function test_waypoint_from_point_via_reflection_casts_coordinates(): void
    {
        $m = new ReflectionMethod(GoogleDrivingRouteService::class, 'waypointFromPoint');
        $m->setAccessible(true);
        $svc = new GoogleDrivingRouteService;

        $w = $m->invoke($svc, ['lat' => '-33.5', 'lng' => 60]);
        $this->assertSame(-33.5, $w['location']['latLng']['latitude']);
        $this->assertSame(60.0, $w['location']['latLng']['longitude']);
    }

    public function test_driving_distance_logs_warning_with_message_when_http_post_throws(): void
    {
        Config::set('carpoolear.google_routes_api_key', 'test-key');
        Config::set('carpoolear.google_routes_region_code', '');

        Http::fake(function () {
            throw new \RuntimeException('simulated transport failure');
        });

        Log::shouldReceive('warning')
            ->once()
            ->with(
                '[google_routes] request exception',
                Mockery::on(function ($context): bool {
                    return is_array($context)
                        && ($context['message'] ?? null) === 'simulated transport failure';
                })
            );

        $svc = new GoogleDrivingRouteService;
        $result = $svc->drivingDistanceAndDuration([
            ['lat' => -34.6, 'lng' => -58.4],
            ['lat' => -31.4, 'lng' => -64.2],
        ]);

        $this->assertNull($result);
    }
}
