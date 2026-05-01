<?php

namespace Tests\Unit\Services;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Mockery;
use STS\Services\GoogleDrivingRouteService;
use Tests\TestCase;

class GoogleDrivingRouteServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
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
