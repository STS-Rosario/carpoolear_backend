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
        mixed $rearMaxTwoPassengers = null,
        array $fuelMeta = []
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

        $unit = $fuelMeta['unit'] ?? FuelPricingResolver::UNIT_LITER;
        $pricePerUnit = array_key_exists('price_per_unit', $fuelMeta)
            ? (float) $fuelMeta['price_per_unit']
            : $fuelPricePerLiter;
        $consumptionPer100 = array_key_exists('consumption_per_100', $fuelMeta)
            ? (float) $fuelMeta['consumption_per_100']
            : ($kilometersPerLiter > 0 ? 100.0 / $kilometersPerLiter : 0.0);

        return [
            'fuel_price_per_liter' => $fuelPricePerLiter,
            'kilometers_per_liter' => $kilometersPerLiter,
            'distance_km' => $distanceKm,
            'liters' => $liters,
            'fuel_quantity' => $liters,
            'fuel_cents' => $fuelCents,
            'tolls_percent' => $tollsPercent,
            'tolls_cents' => $tollsCents,
            'sellado_cents' => $includedSelladoCents,
            'includes_sellado' => $includesSellado,
            'total_cents' => $totalCents,
            'occupants' => $occupants,
            'per_person_cents' => $perPersonCents,
            'fuel_type' => $fuelMeta['fuel_type'] ?? null,
            'fuel_consumption' => $fuelMeta['fuel_consumption'] ?? null,
            'fuel_unit' => $unit,
            'fuel_consumption_per_100' => $consumptionPer100,
            'fuel_price_per_unit' => $pricePerUnit,
        ];
    }

    /**
     * @return array{
     *     fuel_type: string,
     *     fuel_consumption: string,
     *     price_per_unit: float,
     *     consumption_per_100: float,
     *     unit: string,
     *     kilometers_per_unit: float
     * }
     */
    public static function resolveFuelInputs(
        ?string $fuelType,
        ?string $fuelConsumption,
        ?float $pricePerUnit,
        ?float $consumptionPer100,
        ?string $unit
    ): array {
        if ($pricePerUnit !== null && $consumptionPer100 !== null) {
            return [
                'fuel_type' => $fuelType ?? 'nafta_premium',
                'fuel_consumption' => $fuelConsumption ?? 'medium',
                'price_per_unit' => (float) $pricePerUnit,
                'consumption_per_100' => (float) $consumptionPer100,
                'unit' => $unit ?? FuelPricingResolver::UNIT_LITER,
                'kilometers_per_unit' => $consumptionPer100 > 0
                    ? (100.0 / (float) $consumptionPer100)
                    : 0.0,
            ];
        }

        return FuelPricingResolver::legacyDefault();
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
        $routeNeedsPayment = $geoService->doStopsRequireSellado($points);
        $includesSellado = (bool) config('carpoolear.module_trip_creation_payment_enabled')
            && $routeNeedsPayment;
        $selladoCents = $includesSellado && $trip->needs_sellado
            ? (int) config('carpoolear.module_trip_creation_payment_amount_cents')
            : 0;

        $fuelInputs = self::resolveFuelInputs(
            $trip->fuel_type ?? null,
            $trip->fuel_consumption ?? null,
            $trip->fuel_price_per_unit !== null ? (float) $trip->fuel_price_per_unit : null,
            $trip->fuel_consumption_per_100 !== null ? (float) $trip->fuel_consumption_per_100 : null,
            $trip->fuel_unit ?? null
        );

        return self::calculate(
            (float) $trip->distance,
            $fuelInputs['price_per_unit'],
            $fuelInputs['kilometers_per_unit'],
            $tollsPercent,
            $selladoCents,
            $includesSellado,
            $trip->rear_max_two_passengers ?? false,
            [
                'fuel_type' => $fuelInputs['fuel_type'],
                'fuel_consumption' => $fuelInputs['fuel_consumption'],
                'unit' => $fuelInputs['unit'],
                'consumption_per_100' => $fuelInputs['consumption_per_100'],
                'price_per_unit' => $fuelInputs['price_per_unit'],
            ]
        );
    }
}
