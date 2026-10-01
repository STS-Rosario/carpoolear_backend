<?php

namespace Tests\Unit\Services\ContributionCheck;

use STS\Models\Trip;
use STS\Services\ContributionCheck\TripMaximumSeatPrice;
use Tests\TestCase;

class TripMaximumSeatPriceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['carpoolear.module_max_price_enabled' => true]);
    }

    private function trip(array $attributes = []): Trip
    {
        return new Trip(array_merge([
            'seat_price_cents' => 1500000,
            'maximum_trip_price_cents' => 10000000,
            'rear_max_two_passengers' => false,
        ], $attributes));
    }

    public function test_splits_the_maximum_trip_price_like_the_creation_cap(): void
    {
        $this->assertSame(2000000, TripMaximumSeatPrice::centsFor($this->trip()));
        $this->assertSame(
            2500000,
            TripMaximumSeatPrice::centsFor($this->trip(['rear_max_two_passengers' => true]))
        );
    }

    public function test_rounds_like_the_creation_cap(): void
    {
        $this->assertSame(67, TripMaximumSeatPrice::centsFor($this->trip(['maximum_trip_price_cents' => 335])));
    }

    public function test_has_no_maximum_when_the_max_price_module_is_disabled(): void
    {
        config(['carpoolear.module_max_price_enabled' => false]);

        $this->assertNull(TripMaximumSeatPrice::centsFor($this->trip()));
    }

    public function test_has_no_maximum_when_it_was_never_computed(): void
    {
        $this->assertNull(TripMaximumSeatPrice::centsFor($this->trip(['maximum_trip_price_cents' => null])));
        $this->assertNull(TripMaximumSeatPrice::centsFor($this->trip(['maximum_trip_price_cents' => 0])));
    }

    public function test_has_no_maximum_for_voluntary_or_missing_contributions(): void
    {
        $this->assertNull(TripMaximumSeatPrice::centsFor($this->trip(['seat_price_cents' => -1])));
        $this->assertNull(TripMaximumSeatPrice::centsFor($this->trip(['seat_price_cents' => 0])));
        $this->assertNull(TripMaximumSeatPrice::centsFor($this->trip(['seat_price_cents' => null])));
    }
}
