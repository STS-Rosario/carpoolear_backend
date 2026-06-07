<?php

namespace Tests\Unit\Helpers;

use PHPUnit\Framework\TestCase;
use STS\Helpers\TripPriceHelper;

class TripPriceHelperTest extends TestCase
{
    public function test_occupants_for_price_calculation_uses_four_when_rear_max_two_is_enabled(): void
    {
        $this->assertSame(4, TripPriceHelper::occupantsForPriceCalculation(true));
        $this->assertSame(4, TripPriceHelper::occupantsForPriceCalculation(1));
        $this->assertSame(4, TripPriceHelper::occupantsForPriceCalculation('1'));
    }

    public function test_occupants_for_price_calculation_uses_five_when_rear_max_two_is_disabled(): void
    {
        $this->assertSame(5, TripPriceHelper::occupantsForPriceCalculation(false));
        $this->assertSame(5, TripPriceHelper::occupantsForPriceCalculation(0));
        $this->assertSame(5, TripPriceHelper::occupantsForPriceCalculation(null));
    }

    public function test_seat_price_cents_from_trip_price_cents_uses_comfort_based_divisor(): void
    {
        $tripPriceCents = 6345351;

        $this->assertSame(
            (int) round($tripPriceCents / 5),
            TripPriceHelper::seatPriceCentsFromTripPriceCents($tripPriceCents, false)
        );
        $this->assertSame(
            (int) round($tripPriceCents / 4),
            TripPriceHelper::seatPriceCentsFromTripPriceCents($tripPriceCents, true)
        );
    }
}
