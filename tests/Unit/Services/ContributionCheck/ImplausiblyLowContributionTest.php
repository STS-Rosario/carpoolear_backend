<?php

namespace Tests\Unit\Services\ContributionCheck;

use STS\Services\ContributionCheck\ImplausiblyLowContribution;
use Tests\TestCase;

class ImplausiblyLowContributionTest extends TestCase
{
    public function test_matches_amounts_over_one_and_under_two_thousand(): void
    {
        $this->assertTrue(ImplausiblyLowContribution::matches(16.0));
        $this->assertTrue(ImplausiblyLowContribution::matches(1.01));
        $this->assertTrue(ImplausiblyLowContribution::matches(1999.99));
    }

    public function test_does_not_match_one_two_thousand_or_missing(): void
    {
        $this->assertFalse(ImplausiblyLowContribution::matches(null));
        $this->assertFalse(ImplausiblyLowContribution::matches(1.0));
        $this->assertFalse(ImplausiblyLowContribution::matches(2000.0));
        $this->assertFalse(ImplausiblyLowContribution::matches(0.0));
        $this->assertFalse(ImplausiblyLowContribution::matches(15000.0));
    }

    public function test_matches_seat_price_cents_in_the_same_range(): void
    {
        $this->assertTrue(ImplausiblyLowContribution::matchesSeatPriceCents(1600));
        $this->assertFalse(ImplausiblyLowContribution::matchesSeatPriceCents(100));
        $this->assertFalse(ImplausiblyLowContribution::matchesSeatPriceCents(200000));
        $this->assertFalse(ImplausiblyLowContribution::matchesSeatPriceCents(0));
        $this->assertFalse(ImplausiblyLowContribution::matchesSeatPriceCents(-1));
        $this->assertFalse(ImplausiblyLowContribution::matchesSeatPriceCents(null));
        $this->assertFalse(ImplausiblyLowContribution::matchesSeatPriceCents(1500000));
    }
}
