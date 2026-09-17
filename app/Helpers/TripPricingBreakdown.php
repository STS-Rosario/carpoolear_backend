<?php

namespace STS\Helpers;

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
}
