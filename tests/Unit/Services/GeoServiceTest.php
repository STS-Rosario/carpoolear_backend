<?php

namespace Tests\Unit\Services;

use STS\Services\GeoService;
use Tests\TestCase;

class GeoServiceTest extends TestCase
{
    private GeoService $geo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->geo = new GeoService;
    }

    public function test_are_points_in_paid_regions_requires_every_point_inside_a_zone(): void
    {
        $inRosario = [-32.95, -60.68];
        $inBuenosAires = [-34.6, -58.45];
        $outside = [0.0, 0.0];

        $this->assertTrue($this->geo->arePointsInPaidRegions([$inRosario, $inBuenosAires]));
        $this->assertFalse($this->geo->arePointsInPaidRegions([$inRosario, $outside]));
    }

    public function test_are_points_in_paid_routes_true_for_configured_region_pair(): void
    {
        $inRosario = [-32.95, -60.68];
        $inBuenosAires = [-34.6, -58.45];

        $this->assertTrue($this->geo->arePointsInPaidRoutes($inRosario, $inBuenosAires));
        $this->assertFalse($this->geo->arePointsInPaidRoutes($inRosario, [0.0, 0.0]));
    }
}
