<?php

namespace Tests\Unit\Services;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use STS\Services\GoogleDrivingRouteService;
use Tests\TestCase;

class GoogleDrivingRouteServiceTest extends TestCase
{
    public function test_is_enabled_depends_on_api_key_presence(): void
    {
        Config::set('carpoolear.google_routes_api_key', '');
        $this->assertFalse((new GoogleDrivingRouteService)->isEnabled());

        Config::set('carpoolear.google_routes_api_key', 'test-key');
        $this->assertTrue((new GoogleDrivingRouteService)->isEnabled());
    }

    public function test_driving_distance_and_duration_returns_null_when_service_is_disabled(): void
    {
        Config::set('carpoolear.google_routes_api_key', '');
        Http::fake();

        $service = new GoogleDrivingRouteService;
        $result = $service->drivingDistanceAndDuration([
            ['lat' => -34.60, 'lng' => -58.40],
            ['lat' => -34.61, 'lng' => -58.41],
        ]);

        $this->assertNull($result);
        Http::assertNothingSent();
    }

    public function test_driving_distance_and_duration_parses_successful_routes_response(): void
    {
        Config::set('carpoolear.google_routes_api_key', 'test-key');
        Http::fake([
            'https://routes.googleapis.com/directions/v2:computeRoutes' => Http::response([
                'routes' => [[
                    'distanceMeters' => 12345,
                    'duration' => '678.4s',
                ]],
            ], 200),
        ]);

        $service = new GoogleDrivingRouteService;
        $result = $service->drivingDistanceAndDuration([
            ['lat' => -34.60, 'lng' => -58.40],
            ['lat' => -34.61, 'lng' => -58.41],
        ]);

        $this->assertSame(['distance' => 12345, 'duration' => 678], $result);
    }

    public function test_driving_distance_and_duration_returns_null_when_less_than_two_points(): void
    {
        Config::set('carpoolear.google_routes_api_key', 'test-key');
        Http::fake();

        $service = new GoogleDrivingRouteService;
        $result = $service->drivingDistanceAndDuration([
            ['lat' => -34.60, 'lng' => -58.40],
        ]);

        $this->assertNull($result);
        Http::assertNothingSent();
    }

    public function test_driving_distance_and_duration_returns_null_when_too_many_points(): void
    {
        Config::set('carpoolear.google_routes_api_key', 'test-key');
        Http::fake();

        $points = [];
        for ($i = 0; $i < 28; $i++) {
            $points[] = ['lat' => -34.60 + ($i * 0.001), 'lng' => -58.40 + ($i * 0.001)];
        }

        $service = new GoogleDrivingRouteService;
        $result = $service->drivingDistanceAndDuration($points);

        $this->assertNull($result);
        Http::assertNothingSent();
    }

    public function test_driving_distance_and_duration_returns_null_on_http_error_response(): void
    {
        Config::set('carpoolear.google_routes_api_key', 'test-key');
        Http::fake([
            'https://routes.googleapis.com/directions/v2:computeRoutes' => Http::response([
                'error' => ['message' => 'quota exceeded'],
            ], 429),
        ]);

        $service = new GoogleDrivingRouteService;
        $result = $service->drivingDistanceAndDuration([
            ['lat' => -34.60, 'lng' => -58.40],
            ['lat' => -34.61, 'lng' => -58.41],
        ]);

        $this->assertNull($result);
        Http::assertSentCount(1);
    }

    public function test_driving_distance_and_duration_returns_null_when_duration_format_is_invalid(): void
    {
        Config::set('carpoolear.google_routes_api_key', 'test-key');
        Http::fake([
            'https://routes.googleapis.com/directions/v2:computeRoutes' => Http::response([
                'routes' => [[
                    'distanceMeters' => 12345,
                    'duration' => '12m', // invalid for parseDurationSeconds (expects trailing "s")
                ]],
            ], 200),
        ]);

        $service = new GoogleDrivingRouteService;
        $result = $service->drivingDistanceAndDuration([
            ['lat' => -34.60, 'lng' => -58.40],
            ['lat' => -34.61, 'lng' => -58.41],
        ]);

        $this->assertNull($result);
        Http::assertSentCount(1);
    }

    public function test_driving_distance_and_duration_returns_null_when_distance_is_missing_in_response(): void
    {
        Config::set('carpoolear.google_routes_api_key', 'test-key');
        Http::fake([
            'https://routes.googleapis.com/directions/v2:computeRoutes' => Http::response([
                'routes' => [[
                    'duration' => '120s',
                ]],
            ], 200),
        ]);

        $service = new GoogleDrivingRouteService;
        $result = $service->drivingDistanceAndDuration([
            ['lat' => -34.60, 'lng' => -58.40],
            ['lat' => -34.61, 'lng' => -58.41],
        ]);

        $this->assertNull($result);
        Http::assertSentCount(1);
    }

    public function test_driving_distance_and_duration_returns_null_when_routes_array_is_empty(): void
    {
        Config::set('carpoolear.google_routes_api_key', 'test-key');
        Http::fake([
            'https://routes.googleapis.com/directions/v2:computeRoutes' => Http::response([
                'routes' => [],
            ], 200),
        ]);

        $service = new GoogleDrivingRouteService;
        $result = $service->drivingDistanceAndDuration([
            ['lat' => -34.60, 'lng' => -58.40],
            ['lat' => -34.61, 'lng' => -58.41],
        ]);

        $this->assertNull($result);
        Http::assertSentCount(1);
    }

    public function test_driving_distance_and_duration_returns_null_when_duration_is_missing_in_response(): void
    {
        Config::set('carpoolear.google_routes_api_key', 'test-key');
        Http::fake([
            'https://routes.googleapis.com/directions/v2:computeRoutes' => Http::response([
                'routes' => [[
                    'distanceMeters' => 12345,
                ]],
            ], 200),
        ]);

        $service = new GoogleDrivingRouteService;
        $result = $service->drivingDistanceAndDuration([
            ['lat' => -34.60, 'lng' => -58.40],
            ['lat' => -34.61, 'lng' => -58.41],
        ]);

        $this->assertNull($result);
        Http::assertSentCount(1);
    }

    public function test_driving_distance_and_duration_sends_origin_and_destination_without_intermediates_for_two_points(): void
    {
        Config::set('carpoolear.google_routes_api_key', 'test-key');
        Config::set('carpoolear.google_routes_region_code', 'AR');
        Http::fake([
            'https://routes.googleapis.com/directions/v2:computeRoutes' => Http::response([
                'routes' => [[
                    'distanceMeters' => 1000,
                    'duration' => '60s',
                ]],
            ], 200),
        ]);

        $service = new GoogleDrivingRouteService;
        $result = $service->drivingDistanceAndDuration([
            ['lat' => -34.60, 'lng' => -58.40],
            ['lat' => -34.61, 'lng' => -58.41],
        ]);

        $this->assertSame(['distance' => 1000, 'duration' => 60], $result);
        Http::assertSent(function ($request) {
            $data = $request->data();

            return $request->url() === 'https://routes.googleapis.com/directions/v2:computeRoutes'
                && isset($data['origin'], $data['destination'])
                && ! isset($data['intermediates'])
                && ($data['regionCode'] ?? null) === 'AR';
        });
    }

    public function test_driving_distance_and_duration_omits_region_code_when_not_configured(): void
    {
        Config::set('carpoolear.google_routes_api_key', 'test-key');
        Config::set('carpoolear.google_routes_region_code', '');
        Http::fake([
            'https://routes.googleapis.com/directions/v2:computeRoutes' => Http::response([
                'routes' => [[
                    'distanceMeters' => 1000,
                    'duration' => '60s',
                ]],
            ], 200),
        ]);

        $service = new GoogleDrivingRouteService;
        $result = $service->drivingDistanceAndDuration([
            ['lat' => -34.60, 'lng' => -58.40],
            ['lat' => -34.61, 'lng' => -58.41],
        ]);

        $this->assertSame(['distance' => 1000, 'duration' => 60], $result);
        Http::assertSent(function ($request) {
            $data = $request->data();

            return ! isset($data['regionCode']);
        });
    }

    public function test_driving_distance_and_duration_returns_null_when_response_json_is_not_array(): void
    {
        Config::set('carpoolear.google_routes_api_key', 'test-key');
        Http::fake([
            'https://routes.googleapis.com/directions/v2:computeRoutes' => Http::response('not-json-object', 200),
        ]);

        $service = new GoogleDrivingRouteService;
        $result = $service->drivingDistanceAndDuration([
            ['lat' => -34.60, 'lng' => -58.40],
            ['lat' => -34.61, 'lng' => -58.41],
        ]);

        $this->assertNull($result);
        Http::assertSentCount(1);
    }
}
