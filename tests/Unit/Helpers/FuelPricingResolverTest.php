<?php

namespace Tests\Unit\Helpers;

use STS\Helpers\FuelPricingResolver;
use Tests\TestCase;

class FuelPricingResolverTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'carpoolear.module_max_price_fuel_price' => 1500,
            'carpoolear.module_max_price_fuel_price_nafta_super' => 1400,
            'carpoolear.module_max_price_fuel_price_nafta_premium' => 1600,
            'carpoolear.module_max_price_fuel_price_diesel' => 1300,
            'carpoolear.module_max_price_fuel_price_diesel_premium' => 1450,
            'carpoolear.module_max_price_fuel_price_gnc' => 500,
            'carpoolear.module_max_price_kilometer_by_liter' => 10,
        ]);
    }

    public function test_legacy_default_is_nafta_premium_medium(): void
    {
        $resolved = FuelPricingResolver::legacyDefault();

        $this->assertSame('nafta_premium', $resolved['fuel_type']);
        $this->assertSame('medium', $resolved['fuel_consumption']);
        $this->assertSame(1600.0, $resolved['price_per_unit']);
        $this->assertSame(10.0, $resolved['consumption_per_100']);
        $this->assertSame('liter', $resolved['unit']);
        $this->assertEqualsWithDelta(10.0, $resolved['kilometers_per_unit'], 0.0001);
    }

    public function test_resolve_nulls_returns_legacy_default(): void
    {
        $this->assertSame(
            FuelPricingResolver::legacyDefault(),
            FuelPricingResolver::resolve(null, null)
        );
    }

    public function test_resolve_nafta_super_low(): void
    {
        $resolved = FuelPricingResolver::resolve('nafta_super', 'low');

        $this->assertSame('nafta_super', $resolved['fuel_type']);
        $this->assertSame('low', $resolved['fuel_consumption']);
        $this->assertSame(1400.0, $resolved['price_per_unit']);
        $this->assertSame(7.0, $resolved['consumption_per_100']);
        $this->assertSame('liter', $resolved['unit']);
        $this->assertEqualsWithDelta(100 / 7, $resolved['kilometers_per_unit'], 0.0001);
    }

    public function test_resolve_nafta_premium_high(): void
    {
        $resolved = FuelPricingResolver::resolve('nafta_premium', 'high');

        $this->assertSame(1600.0, $resolved['price_per_unit']);
        $this->assertSame(12.0, $resolved['consumption_per_100']);
        $this->assertSame('liter', $resolved['unit']);
    }

    public function test_resolve_diesel_medium(): void
    {
        $resolved = FuelPricingResolver::resolve('diesel', 'medium');

        $this->assertSame(1300.0, $resolved['price_per_unit']);
        $this->assertSame(8.0, $resolved['consumption_per_100']);
        $this->assertSame('liter', $resolved['unit']);
    }

    public function test_resolve_diesel_premium_high(): void
    {
        $resolved = FuelPricingResolver::resolve('diesel_premium', 'high');

        $this->assertSame(1450.0, $resolved['price_per_unit']);
        $this->assertSame(10.0, $resolved['consumption_per_100']);
        $this->assertSame('liter', $resolved['unit']);
    }

    public function test_resolve_gnc_medium(): void
    {
        $resolved = FuelPricingResolver::resolve('gnc', 'medium');

        $this->assertSame(500.0, $resolved['price_per_unit']);
        $this->assertSame(9.0, $resolved['consumption_per_100']);
        $this->assertSame('m3', $resolved['unit']);
        $this->assertEqualsWithDelta(100 / 9, $resolved['kilometers_per_unit'], 0.0001);
    }

    public function test_nafta_premium_price_falls_back_to_legacy_module_max_price_fuel_price(): void
    {
        config([
            'carpoolear.module_max_price_fuel_price_nafta_premium' => null,
            'carpoolear.module_max_price_fuel_price' => 1555,
        ]);

        $resolved = FuelPricingResolver::resolve('nafta_premium', 'medium');

        $this->assertSame(1555.0, $resolved['price_per_unit']);
    }

    public function test_consumption_tables_are_exposed(): void
    {
        $tables = FuelPricingResolver::consumptionTables();

        $this->assertSame([
            'low' => 7.0,
            'medium' => 10.0,
            'high' => 12.0,
        ], $tables['nafta']);
        $this->assertSame([
            'low' => 6.0,
            'medium' => 8.0,
            'high' => 10.0,
        ], $tables['diesel']);
        $this->assertSame([
            'low' => 7.0,
            'medium' => 9.0,
            'high' => 11.0,
        ], $tables['gnc']);
    }
}
