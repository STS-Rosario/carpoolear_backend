<?php

namespace Tests\Unit\Services;

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
}
