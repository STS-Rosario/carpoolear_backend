<?php

namespace STS\Helpers;

class FuelPricingResolver
{
    public const FUEL_TYPES = [
        'gnc',
        'diesel',
        'diesel_premium',
        'nafta_super',
        'nafta_premium',
    ];

    public const CONSUMPTION_LEVELS = [
        'low',
        'medium',
        'high',
    ];

    public const UNIT_LITER = 'liter';

    public const UNIT_M3 = 'm3';

    /** @var array<string, array<string, float>> */
    private const CONSUMPTION_TABLES = [
        'nafta' => [
            'low' => 7.0,
            'medium' => 10.0,
            'high' => 12.0,
        ],
        'diesel' => [
            'low' => 6.0,
            'medium' => 8.0,
            'high' => 10.0,
        ],
        'gnc' => [
            'low' => 7.0,
            'medium' => 9.0,
            'high' => 11.0,
        ],
    ];

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
    public static function legacyDefault(): array
    {
        return self::resolve('nafta_premium', 'medium');
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
    public static function resolve(?string $fuelType, ?string $consumption): array
    {
        if ($fuelType === null && $consumption === null) {
            $fuelType = 'nafta_premium';
            $consumption = 'medium';
        } elseif (
            $fuelType === null
            || $consumption === null
            || ! in_array($fuelType, self::FUEL_TYPES, true)
            || ! in_array($consumption, self::CONSUMPTION_LEVELS, true)
        ) {
            return self::resolve('nafta_premium', 'medium');
        }

        $family = self::familyFor($fuelType);
        $consumptionPer100 = self::CONSUMPTION_TABLES[$family][$consumption];
        $unit = $family === 'gnc' ? self::UNIT_M3 : self::UNIT_LITER;
        $pricePerUnit = self::priceFor($fuelType);

        return [
            'fuel_type' => $fuelType,
            'fuel_consumption' => $consumption,
            'price_per_unit' => $pricePerUnit,
            'consumption_per_100' => $consumptionPer100,
            'unit' => $unit,
            'kilometers_per_unit' => $consumptionPer100 > 0 ? (100.0 / $consumptionPer100) : 0.0,
        ];
    }

    /**
     * @return array<string, array<string, float>>
     */
    public static function consumptionTables(): array
    {
        return self::CONSUMPTION_TABLES;
    }

    private static function familyFor(string $fuelType): string
    {
        return match ($fuelType) {
            'gnc' => 'gnc',
            'diesel', 'diesel_premium' => 'diesel',
            default => 'nafta',
        };
    }

    private static function priceFor(string $fuelType): float
    {
        return match ($fuelType) {
            'nafta_super' => (float) config('carpoolear.module_max_price_fuel_price_nafta_super'),
            'nafta_premium' => self::naftaPremiumPrice(),
            'diesel' => (float) config('carpoolear.module_max_price_fuel_price_diesel'),
            'diesel_premium' => (float) config('carpoolear.module_max_price_fuel_price_diesel_premium'),
            'gnc' => (float) config('carpoolear.module_max_price_fuel_price_gnc'),
            default => self::naftaPremiumPrice(),
        };
    }

    private static function naftaPremiumPrice(): float
    {
        $premium = config('carpoolear.module_max_price_fuel_price_nafta_premium');
        if ($premium === null || $premium === '') {
            return (float) config('carpoolear.module_max_price_fuel_price', 1500);
        }

        return (float) $premium;
    }
}
