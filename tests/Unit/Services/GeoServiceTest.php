<?php

namespace Tests\Unit\Services;

use STS\Services\GeoService;
use Tests\TestCase;

class GeoServiceTest extends TestCase
{
    private GeoService $geo;

    /**
     * Canonical billing polygons — must stay byte-for-byte aligned with {@see GeoService::__construct()}
     * so Infection cannot survive DecrementFloat, IncrementFloat, or RemoveArrayItem
     * on those literals without breaking this contract test.
     *
     * @return list<list<array{0: float, 1: float}>>
     */
    private static function expectedPaidRegionRings(): array
    {
        return [
            [
                [-34.259825, -58.218597],
                [-34.282523, -58.883270],
                [-34.515947, -59.039825],
                [-35.023585, -58.460297],
                [-34.762259, -58.103241],
                [-34.259825, -58.218597],
            ],
            [
                [-32.824045, -60.698163],
                [-32.838470, -60.813519],
                [-32.899602, -60.943295],
                [-33.081596, -60.926816],
                [-33.159807, -60.814206],
                [-33.163256, -60.674130],
                [-33.052825, -60.549161],
                [-33.001012, -60.608212],
                [-32.951474, -60.615079],
                [-32.939950, -60.626065],
                [-32.905367, -60.671384],
                [-32.870771, -60.672757],
                [-32.824045, -60.698163],
            ],
            [
                [-31.273659, -64.278703],
                [-31.328809, -64.358354],
                [-31.494651, -64.258791],
                [-31.498749, -64.089189],
                [-31.414989, -64.056917],
                [-31.346403, -64.061724],
                [-31.273659, -64.278703],
            ],
            [
                [-37.793679, -57.450653],
                [-37.856594, -57.762390],
                [-38.021221, -57.879120],
                [-38.229721, -57.693725],
                [-38.052588, -57.475372],
                [-37.793679, -57.450653],
            ],
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->geo = new GeoService;
    }

    public function test_get_paid_regions_matches_billing_polygon_vertices(): void
    {
        $regions = $this->geo->getPaidRegions();
        $expected = self::expectedPaidRegionRings();

        $this->assertSame(count($expected), count($regions));

        foreach ($expected as $ringIndex => $expectedRing) {
            $this->assertSame(
                count($expectedRing),
                count($regions[$ringIndex]),
                'vertex count for paid region ring '.$ringIndex
            );
            foreach ($expectedRing as $vertexIndex => $expectedVertex) {
                $actualVertex = $regions[$ringIndex][$vertexIndex];
                $this->assertSame(
                    $expectedVertex[0],
                    $actualVertex[0],
                    'lat ring '.$ringIndex.' vertex '.$vertexIndex
                );
                $this->assertSame(
                    $expectedVertex[1],
                    $actualVertex[1],
                    'lng ring '.$ringIndex.' vertex '.$vertexIndex
                );
            }
        }
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

    public function test_are_points_in_paid_routes_false_when_regions_have_no_billing_route_pair(): void
    {
        $inCordoba = [-31.4, -64.15];
        $inMarDelPlata = [-38.0, -57.6];

        $this->assertTrue($this->geo->arePointsInPaidRegions([$inCordoba, $inMarDelPlata]));
        $this->assertFalse($this->geo->arePointsInPaidRoutes($inCordoba, $inMarDelPlata));
    }

    public function test_do_stops_require_sellado_requires_two_distinct_stops_in_a_paid_zone(): void
    {
        $inRosario = [-32.95, -60.68];
        $inBuenosAires = [-34.6, -58.45];
        $outside = [0.0, 0.0];

        $this->assertFalse($this->geo->doStopsRequireSellado([]));
        $this->assertFalse($this->geo->doStopsRequireSellado([$outside]));
        $this->assertFalse($this->geo->doStopsRequireSellado([$inRosario]));
        $this->assertFalse($this->geo->doStopsRequireSellado([$inRosario, $outside, $outside]));
        $this->assertTrue($this->geo->doStopsRequireSellado([$inRosario, $inBuenosAires]));
        $this->assertTrue($this->geo->doStopsRequireSellado([$inRosario, $inBuenosAires, $outside]));
    }
}
