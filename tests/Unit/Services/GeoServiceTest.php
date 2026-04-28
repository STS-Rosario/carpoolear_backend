<?php

namespace Tests\Unit\Services;

use STS\Services\GeoService;
use Tests\TestCase;

class GeoServiceTest extends TestCase
{
    public function test_get_paid_regions_returns_non_empty_polygon_list(): void
    {
        $service = new GeoService;
        $regions = $service->getPaidRegions();

        $this->assertNotEmpty($regions);
        $this->assertIsArray($regions[0]);
        $this->assertGreaterThanOrEqual(4, count($regions[0]));
    }

    public function test_is_point_in_paid_zone_returns_false_for_far_away_point(): void
    {
        $service = new GeoService;

        $this->assertFalse($service->isPointInPaidZone([0.0, 0.0]));
    }

    public function test_do_stops_require_sellado_returns_false_when_no_stop_is_in_paid_zone(): void
    {
        $service = new GeoService;

        $this->assertFalse($service->doStopsRequireSellado([
            [0.0, 0.0],
            [1.0, 1.0],
            [-1.0, -1.0],
        ]));
    }

    public function test_are_points_in_paid_regions_returns_true_for_empty_input(): void
    {
        $service = new GeoService;

        $this->assertTrue($service->arePointsInPaidRegions([]));
    }

    public function test_are_points_in_paid_routes_returns_false_when_one_point_is_outside_paid_regions(): void
    {
        $service = new GeoService;

        // First point is near Buenos Aires polygon, second point is clearly outside any configured region.
        $this->assertFalse($service->arePointsInPaidRoutes(
            [-34.60, -58.40],
            [0.0, 0.0]
        ));
    }
}
