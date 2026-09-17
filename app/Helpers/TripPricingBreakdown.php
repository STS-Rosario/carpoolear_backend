<?php

namespace STS\Helpers;

use STS\Models\Trip;
use STS\Services\GeoService;

class TripPricingBreakdown
{
    public static function calculate(
        float $distanceMeters,
        float $fuelPricePerLiter,
        float $kilometersPerLiter,
        float $tollsPercent,
        int $selladoCents,
        bool $includesSellado,
        mixed $rearMaxTwoPassengers = null
    ): array {
        $distanceKm = $distanceMeters / 1000;
        $liters = $kilometersPerLiter > 0 ? $distanceKm / $kilometersPerLiter : 0.0;
        $pricePerKilometer = $kilometersPerLiter > 0
            ? $fuelPricePerLiter / $kilometersPerLiter
            : 0.0;
        $fuelCents = (int) round($distanceKm * $pricePerKilometer * 100);
        $tollsCents = (int) round($fuelCents * ($tollsPercent / 100));
        $includedSelladoCents = $includesSellado ? $selladoCents : 0;
        $totalCents = $fuelCents + $tollsCents + $includedSelladoCents;

        $occupants = null;
        $perPersonCents = null;
        if ($rearMaxTwoPassengers !== null) {
            $occupants = TripPriceHelper::occupantsForPriceCalculation($rearMaxTwoPassengers);
            $perPersonCents = TripPriceHelper::seatPriceCentsFromTripPriceCents(
                $totalCents,
                $rearMaxTwoPassengers
            );
        }

        return [
            'fuel_price_per_liter' => $fuelPricePerLiter,
            'kilometers_per_liter' => $kilometersPerLiter,
            'distance_km' => $distanceKm,
            'liters' => $liters,
            'fuel_cents' => $fuelCents,
            'tolls_percent' => $tollsPercent,
            'tolls_cents' => $tollsCents,
            'sellado_cents' => $includedSelladoCents,
            'includes_sellado' => $includesSellado,
            'total_cents' => $totalCents,
            'occupants' => $occupants,
            'per_person_cents' => $perPersonCents,
        ];
    }

    public static function forTrip(Trip $trip, ?GeoService $geoService = null): array
    {
        $geoService ??= app(GeoService::class);
        $points = $trip->points
            ->map(fn ($point) => [(float) $point->lat, (float) $point->lng])
            ->all();
        $inCostaAtlantica = $geoService->hasExactlyOneStopInCostaAtlanticaZone($points);
        $tollsPercent = $inCostaAtlantica
            ? (float) config('carpoolear.module_max_price_price_variance_tolls_costa_atlantica', 25)
            : (float) config('carpoolear.module_max_price_price_variance_tolls', 0);
        $includesSellado = (bool) config('carpoolear.module_trip_creation_payment_enabled');
        $selladoCents = $includesSellado
            ? (int) config('carpoolear.module_trip_creation_payment_amount_cents')
            : 0;

        return self::calculate(
            (float) $trip->distance,
            (float) config('carpoolear.module_max_price_fuel_price'),
            (float) config('carpoolear.module_max_price_kilometer_by_liter'),
            $tollsPercent,
            $selladoCents,
            $includesSellado,
            $trip->rear_max_two_passengers ?? false
        );
    }
}
