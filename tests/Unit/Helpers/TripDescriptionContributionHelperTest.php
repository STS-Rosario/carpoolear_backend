<?php

namespace Tests\Unit\Helpers;

use PHPUnit\Framework\TestCase;
use STS\Helpers\TripDescriptionContributionHelper;

class TripDescriptionContributionHelperTest extends TestCase
{
    public function test_potential_excess_detects_dollar_amount_above_seat_price(): void
    {
        $seatPriceCents = 1500000; // $15.000

        $this->assertSame(
            2400000,
            TripDescriptionContributionHelper::potentialExcessContributionCents(
                'La contribución es de $24000 por persona',
                $seatPriceCents
            )
        );
    }

    public function test_potential_excess_detects_k_suffix_amounts(): void
    {
        $seatPriceCents = 1500000;

        $this->assertSame(
            2400000,
            TripDescriptionContributionHelper::potentialExcessContributionCents(
                'Pago $24K cada uno',
                $seatPriceCents
            )
        );
    }

    public function test_potential_excess_detects_lucas_amounts(): void
    {
        $seatPriceCents = 1500000;

        $this->assertSame(
            2400000,
            TripDescriptionContributionHelper::potentialExcessContributionCents(
                'Son 24 lucas por persona',
                $seatPriceCents
            )
        );
    }

    public function test_potential_excess_returns_null_when_description_amount_is_not_higher(): void
    {
        $seatPriceCents = 1500000;

        $this->assertNull(
            TripDescriptionContributionHelper::potentialExcessContributionCents(
                'Contribución $15000',
                $seatPriceCents
            )
        );
        $this->assertNull(
            TripDescriptionContributionHelper::potentialExcessContributionCents(
                'Solo 10 lucas',
                $seatPriceCents
            )
        );
    }

    public function test_potential_excess_returns_null_for_voluntary_or_non_positive_seat_price(): void
    {
        $this->assertNull(
            TripDescriptionContributionHelper::potentialExcessContributionCents(
                '$24000',
                -1
            )
        );
        $this->assertNull(
            TripDescriptionContributionHelper::potentialExcessContributionCents(
                '$24000',
                0
            )
        );
    }

    public function test_has_potential_excess_contribution_reflects_potential_amount(): void
    {
        $this->assertTrue(
            TripDescriptionContributionHelper::hasPotentialExcessContribution(
                '$24000',
                1500000
            )
        );
        $this->assertFalse(
            TripDescriptionContributionHelper::hasPotentialExcessContribution(
                '$15000',
                1500000
            )
        );
    }

    public function test_average_contribution_cents_uses_recommended_trip_price_per_seat(): void
    {
        $trip = new \STS\Models\Trip([
            'recommended_trip_price_cents' => 5000000,
            'rear_max_two_passengers' => false,
        ]);

        $this->assertSame(
            1000000,
            TripDescriptionContributionHelper::averageContributionCents($trip)
        );
    }

    public function test_average_contribution_cents_returns_null_without_recommended_price(): void
    {
        $trip = new \STS\Models\Trip([
            'recommended_trip_price_cents' => null,
        ]);

        $this->assertNull(
            TripDescriptionContributionHelper::averageContributionCents($trip)
        );
    }

    public function test_excess_contribution_percentage_measures_asked_amount_over_average(): void
    {
        $this->assertSame(
            100,
            TripDescriptionContributionHelper::excessContributionPercentage(
                2000000,
                1000000
            )
        );
        $this->assertSame(
            140,
            TripDescriptionContributionHelper::excessContributionPercentage(
                2400000,
                1000000
            )
        );
    }

    public function test_excess_contribution_percentage_returns_null_without_valid_average(): void
    {
        $this->assertNull(
            TripDescriptionContributionHelper::excessContributionPercentage(
                2000000,
                null
            )
        );
        $this->assertNull(
            TripDescriptionContributionHelper::excessContributionPercentage(
                2000000,
                0
            )
        );
    }
}
