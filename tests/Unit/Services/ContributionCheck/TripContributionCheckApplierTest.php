<?php

namespace Tests\Unit\Services\ContributionCheck;

use STS\Models\Trip;
use STS\Services\ContributionCheck\ContributionCheckResult;
use STS\Services\ContributionCheck\TripContributionCheckApplier;
use STS\Support\TripExcessContributionStatus;
use Tests\TestCase;

class TripContributionCheckApplierTest extends TestCase
{
    private function trip(array $overrides = []): Trip
    {
        return Trip::factory()->create(array_merge([
            'seat_price_cents' => 1500000,
            'description' => 'La contribución es de 24 lucas por persona',
            'recommended_trip_price_cents' => 5000000,
            'rear_max_two_passengers' => false,
        ], $overrides));
    }

    private function apply(Trip $trip, ContributionCheckResult $result): Trip
    {
        (new TripContributionCheckApplier)->apply($trip, $result);

        return $trip->fresh();
    }

    public function test_excess_flags_trip_for_admin_review_with_amounts(): void
    {
        $trip = $this->apply($this->trip(), new ContributionCheckResult(24000.0, true, false));

        $this->assertTrue($trip->has_potential_excess_contribution);
        $this->assertSame(24000.0, $trip->suspected_contribution);
        $this->assertFalse($trip->phone_in_description);
        $this->assertSame(2400000, $trip->description_potential_seat_price_cents);
        $this->assertSame(1000000, $trip->average_contribution_cents);
        $this->assertSame(140, $trip->excess_contribution_percentage);
        $this->assertSame(TripExcessContributionStatus::PENDIENTE, $trip->exceso_contribucion_status);
    }

    public function test_phone_in_description_alone_flags_trip_for_admin_review(): void
    {
        $trip = $this->apply(
            $this->trip(['description' => 'Llamame al tres cuatro uno, cinco cinco cinco']),
            new ContributionCheckResult(null, false, true)
        );

        $this->assertTrue($trip->has_potential_excess_contribution);
        $this->assertTrue($trip->phone_in_description);
        $this->assertNull($trip->suspected_contribution);
        $this->assertNull($trip->description_potential_seat_price_cents);
        $this->assertNull($trip->excess_contribution_percentage);
        $this->assertSame(TripExcessContributionStatus::PENDIENTE, $trip->exceso_contribucion_status);
    }

    public function test_clean_result_clears_previous_flags_and_keeps_suspected_amount(): void
    {
        $trip = $this->trip([
            'description' => 'Contribución $15000',
            'has_potential_excess_contribution' => true,
            'description_potential_seat_price_cents' => 2400000,
            'average_contribution_cents' => 1000000,
            'excess_contribution_percentage' => 140,
            'exceso_contribucion_status' => TripExcessContributionStatus::PENDIENTE,
            'phone_in_description' => true,
        ]);

        $trip = $this->apply($trip, new ContributionCheckResult(15000.0, false, false));

        $this->assertFalse($trip->has_potential_excess_contribution);
        $this->assertFalse($trip->phone_in_description);
        $this->assertSame(15000.0, $trip->suspected_contribution);
        $this->assertNull($trip->description_potential_seat_price_cents);
        $this->assertNull($trip->average_contribution_cents);
        $this->assertNull($trip->excess_contribution_percentage);
        $this->assertNull($trip->exceso_contribucion_status);
    }

    public function test_keeps_admin_status_when_trip_is_flagged_again(): void
    {
        $trip = $this->trip(['exceso_contribucion_status' => TripExcessContributionStatus::EN_PROCESO]);

        $trip = $this->apply($trip, new ContributionCheckResult(24000.0, true, false));

        $this->assertSame(TripExcessContributionStatus::EN_PROCESO, $trip->exceso_contribucion_status);
    }

    public function test_voluntary_contribution_trips_cannot_exceed_the_max(): void
    {
        $trip = $this->apply(
            $this->trip(['seat_price_cents' => -1]),
            new ContributionCheckResult(24000.0, true, false)
        );

        $this->assertFalse($trip->has_potential_excess_contribution);
        $this->assertNull($trip->description_potential_seat_price_cents);
        $this->assertSame(24000.0, $trip->suspected_contribution);
    }

    public function test_ignores_excess_claims_when_the_suspected_amount_is_within_the_max(): void
    {
        $trip = $this->apply($this->trip(), new ContributionCheckResult(15000.0, true, false));

        $this->assertFalse($trip->has_potential_excess_contribution);
        $this->assertNull($trip->exceso_contribucion_status);
    }

    public function test_excess_without_an_amount_is_flagged_without_potential_price(): void
    {
        $trip = $this->apply($this->trip(), new ContributionCheckResult(null, true, false));

        $this->assertTrue($trip->has_potential_excess_contribution);
        $this->assertNull($trip->description_potential_seat_price_cents);
        $this->assertNull($trip->excess_contribution_percentage);
    }

    public function test_excess_percentage_is_never_negative(): void
    {
        $trip = $this->apply(
            $this->trip(['recommended_trip_price_cents' => 15000000]),
            new ContributionCheckResult(20000.0, true, false)
        );

        $this->assertSame(3000000, $trip->average_contribution_cents);
        $this->assertSame(0, $trip->excess_contribution_percentage);
    }
}
