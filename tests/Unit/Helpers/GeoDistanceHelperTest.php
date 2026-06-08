<?php

namespace Tests\Unit\Helpers;

use STS\Helpers\GeoDistanceHelper;
use Tests\TestCase;

class GeoDistanceHelperTest extends TestCase
{
    public function test_distance_km_returns_zero_for_same_point(): void
    {
        $this->assertSame(0.0, GeoDistanceHelper::distanceKm(-34.6037, -58.3816, -34.6037, -58.3816));
    }

    public function test_distance_km_is_approximately_ten_km_for_known_offset(): void
    {
        // ~10 km north from a reference point (1 degree lat ≈ 111 km)
        $lat = -34.6037;
        $lng = -58.3816;
        $latOffset = $lat + (10.0 / 111.0);

        $distance = GeoDistanceHelper::distanceKm($lat, $lng, $latOffset, $lng);

        $this->assertGreaterThan(9.5, $distance);
        $this->assertLessThan(10.5, $distance);
    }

    public function test_is_within_km_returns_true_when_inside_radius(): void
    {
        $lat = -34.6037;
        $lng = -58.3816;
        $latOffset = $lat + (5.0 / 111.0);

        $this->assertTrue(GeoDistanceHelper::isWithinKm($lat, $lng, $latOffset, $lng, 10.0));
    }

    public function test_is_within_km_returns_false_when_outside_radius(): void
    {
        $lat = -34.6037;
        $lng = -58.3816;
        $latOffset = $lat + (15.0 / 111.0);

        $this->assertFalse(GeoDistanceHelper::isWithinKm($lat, $lng, $latOffset, $lng, 10.0));
    }
}
