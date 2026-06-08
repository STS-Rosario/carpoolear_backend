<?php

namespace STS\Helpers;

class GeoDistanceHelper
{
    private const EARTH_RADIUS_KM = 6371.0;

    public static function distanceKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $lat1Rad = deg2rad($lat1);
        $lat2Rad = deg2rad($lat2);
        $deltaLat = deg2rad($lat2 - $lat1);
        $deltaLng = deg2rad($lng2 - $lng1);

        $a = sin($deltaLat / 2) ** 2
            + cos($lat1Rad) * cos($lat2Rad) * sin($deltaLng / 2) ** 2;
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return self::EARTH_RADIUS_KM * $c;
    }

    public static function isWithinKm(
        float $lat1,
        float $lng1,
        float $lat2,
        float $lng2,
        float $radiusKm
    ): bool {
        return self::distanceKm($lat1, $lng1, $lat2, $lng2) <= $radiusKm;
    }
}
