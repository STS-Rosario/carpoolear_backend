<?php

namespace Tests\Unit\Helpers;

use PHPUnit\Framework\TestCase;
use STS\Helpers\TripPricingBreakdown;

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
}
