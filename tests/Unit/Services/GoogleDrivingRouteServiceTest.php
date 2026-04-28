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
}
