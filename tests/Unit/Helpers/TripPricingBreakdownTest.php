<?php

namespace Tests\Unit\Helpers;

use STS\Helpers\TripPricingBreakdown;
use Tests\TestCase;

class TripPricingBreakdownTest extends TestCase
{
    public function test_calculate_returns_fuel_liters_tolls_sellado_and_total_line_items(): void
    {
        $breakdown = TripPricingBreakdown::calculate(
            1000.0,
            1000.0,
            10.0,
            10.0,
            50,
            true
        );

        $this->assertSame(1000.0, $breakdown['fuel_price_per_liter']);
        $this->assertSame(10.0, $breakdown['kilometers_per_liter']);
        $this->assertEqualsWithDelta(1.0, $breakdown['distance_km'], 0.0001);
        $this->assertEqualsWithDelta(0.1, $breakdown['liters'], 0.0001);
        $this->assertSame(10000, $breakdown['fuel_cents']);
        $this->assertSame(10.0, $breakdown['tolls_percent']);
        $this->assertSame(1000, $breakdown['tolls_cents']);
        $this->assertSame(50, $breakdown['sellado_cents']);
        $this->assertTrue($breakdown['includes_sellado']);
        $this->assertSame(11050, $breakdown['total_cents']);
        $this->assertNull($breakdown['occupants']);
        $this->assertNull($breakdown['per_person_cents']);
    }

    public function test_calculate_omits_sellado_amount_when_module_is_off(): void
    {
        $breakdown = TripPricingBreakdown::calculate(
            1000.0,
            1000.0,
            10.0,
            10.0,
            0,
            false
        );

        $this->assertFalse($breakdown['includes_sellado']);
        $this->assertSame(0, $breakdown['sellado_cents']);
        $this->assertSame(11000, $breakdown['total_cents']);
    }

    public function test_calculate_keeps_includes_sellado_when_module_on_and_amount_is_zero(): void
    {
        $breakdown = TripPricingBreakdown::calculate(
            1000.0,
            1000.0,
            10.0,
            10.0,
            0,
            true
        );

        $this->assertTrue($breakdown['includes_sellado']);
        $this->assertSame(0, $breakdown['sellado_cents']);
        $this->assertSame(11000, $breakdown['total_cents']);
    }

    public function test_calculate_uses_four_occupants_when_rear_max_two_is_enabled(): void
    {
        $breakdown = TripPricingBreakdown::calculate(
            1000.0,
            1000.0,
            10.0,
            10.0,
            50,
            true,
            true
        );

        $this->assertSame(4, $breakdown['occupants']);
        $this->assertSame(2763, $breakdown['per_person_cents']);
    }

    public function test_calculate_uses_five_occupants_when_rear_max_two_is_disabled(): void
    {
        $breakdown = TripPricingBreakdown::calculate(
            1000.0,
            1000.0,
            10.0,
            10.0,
            50,
            true,
            false
        );

        $this->assertSame(5, $breakdown['occupants']);
        $this->assertSame(2210, $breakdown['per_person_cents']);
    }

    public function test_calculate_accepts_tolls_percent_as_an_input(): void
    {
        $breakdown = TripPricingBreakdown::calculate(
            100000.0,
            1000.0,
            10.0,
            25.0,
            0,
            false
        );

        $this->assertSame(25.0, $breakdown['tolls_percent']);
        $this->assertSame(1000000, $breakdown['fuel_cents']);
        $this->assertSame(250000, $breakdown['tolls_cents']);
        $this->assertSame(1250000, $breakdown['total_cents']);
    }

    public function test_resolve_fuel_inputs_uses_legacy_default_when_snapshot_missing(): void
    {
        $inputs = TripPricingBreakdown::resolveFuelInputs(null, null, null, null, null);

        $this->assertSame('nafta_premium', $inputs['fuel_type']);
        $this->assertSame('medium', $inputs['fuel_consumption']);
        $this->assertSame('liter', $inputs['unit']);
        $this->assertSame(10.0, $inputs['consumption_per_100']);
        $this->assertEqualsWithDelta(10.0, $inputs['kilometers_per_unit'], 0.0001);
    }

    public function test_resolve_fuel_inputs_prefers_snapshot_values(): void
    {
        $inputs = TripPricingBreakdown::resolveFuelInputs(
            'gnc',
            'high',
            500.0,
            11.0,
            'm3'
        );

        $this->assertSame('gnc', $inputs['fuel_type']);
        $this->assertSame('high', $inputs['fuel_consumption']);
        $this->assertSame(500.0, $inputs['price_per_unit']);
        $this->assertSame(11.0, $inputs['consumption_per_100']);
        $this->assertSame('m3', $inputs['unit']);
        $this->assertEqualsWithDelta(100 / 11, $inputs['kilometers_per_unit'], 0.0001);
    }

    public function test_calculate_includes_fuel_meta_and_keeps_liters_alias(): void
    {
        $breakdown = TripPricingBreakdown::calculate(
            100000.0,
            500.0,
            100 / 9,
            10.0,
            0,
            false,
            null,
            [
                'fuel_type' => 'gnc',
                'fuel_consumption' => 'medium',
                'unit' => 'm3',
                'consumption_per_100' => 9.0,
                'price_per_unit' => 500.0,
            ]
        );

        $this->assertSame('gnc', $breakdown['fuel_type']);
        $this->assertSame('medium', $breakdown['fuel_consumption']);
        $this->assertSame('m3', $breakdown['fuel_unit']);
        $this->assertSame(9.0, $breakdown['fuel_consumption_per_100']);
        $this->assertSame(500.0, $breakdown['fuel_price_per_unit']);
        $this->assertEqualsWithDelta(9.0, $breakdown['liters'], 0.0001);
        $this->assertEqualsWithDelta(9.0, $breakdown['fuel_quantity'], 0.0001);
    }
}
