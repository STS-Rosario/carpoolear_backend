<?php

namespace Tests\Unit\Services\ContributionCheck;

use STS\Models\Trip;
use STS\Services\ContributionCheck\ContributionCheckResult;
use STS\Services\ContributionCheck\TripContributionCheckApplier;
use STS\Support\TripExcessContributionStatus;
use Tests\TestCase;

class TripContributionCheckApplierTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['carpoolear.module_max_price_enabled' => true]);
    }

    /**
     * Chosen contribution $15000; maximum allowed $20000 per seat ($100000 trip / 5).
     */
    private function trip(array $overrides = []): Trip
    {
        return Trip::factory()->create(array_merge([
            'seat_price_cents' => 1500000,
            'maximum_trip_price_cents' => 10000000,
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

    public function test_ignores_excess_claims_when_the_suspected_amount_equals_the_max(): void
    {
        $trip = $this->apply($this->trip(), new ContributionCheckResult(20000.0, true, false));

        $this->assertFalse($trip->has_potential_excess_contribution);
        $this->assertNull($trip->exceso_contribucion_status);
    }

    public function test_asking_more_than_the_chosen_price_but_within_the_max_is_not_an_excess(): void
    {
        $trip = $this->apply($this->trip(), new ContributionCheckResult(18000.0, true, false));

        $this->assertFalse($trip->has_potential_excess_contribution);
        $this->assertNull($trip->description_potential_seat_price_cents);
        $this->assertSame(18000.0, $trip->suspected_contribution);
    }

    public function test_cannot_exceed_when_the_max_price_module_is_disabled(): void
    {
        config(['carpoolear.module_max_price_enabled' => false]);

        $trip = $this->apply($this->trip(), new ContributionCheckResult(24000.0, true, false));

        $this->assertFalse($trip->has_potential_excess_contribution);
        $this->assertNull($trip->exceso_contribucion_status);
    }

    public function test_cannot_exceed_when_the_trip_has_no_computed_maximum(): void
    {
        $trip = $this->apply(
            $this->trip(['maximum_trip_price_cents' => null]),
            new ContributionCheckResult(24000.0, true, false)
        );

        $this->assertFalse($trip->has_potential_excess_contribution);
    }

    public function test_uses_the_rear_seat_comfort_divisor_for_the_max(): void
    {
        // $100000 / 4 occupants = $25000 per seat.
        $trip = $this->apply(
            $this->trip(['rear_max_two_passengers' => true]),
            new ContributionCheckResult(24000.0, true, false)
        );

        $this->assertFalse($trip->has_potential_excess_contribution);
    }

    public function test_excess_without_an_amount_is_flagged_without_potential_price(): void
    {
        $trip = $this->apply($this->trip(), new ContributionCheckResult(null, true, false));

        $this->assertTrue($trip->has_potential_excess_contribution);
        $this->assertNull($trip->description_potential_seat_price_cents);
        $this->assertNull($trip->excess_contribution_percentage);
    }

    public function test_implausibly_low_seat_price_flags_trip_even_with_a_clean_llm_result(): void
    {
        $trip = $this->apply(
            $this->trip(['seat_price_cents' => 1600, 'description' => 'Salgo puntual']),
            new ContributionCheckResult(null, false, false)
        );

        $this->assertTrue($trip->has_potential_excess_contribution);
        $this->assertNull($trip->description_potential_seat_price_cents);
        $this->assertNull($trip->excess_contribution_percentage);
        $this->assertSame(TripExcessContributionStatus::PENDIENTE, $trip->exceso_contribucion_status);
    }

    public function test_clean_result_does_not_clear_an_implausibly_low_seat_price_flag(): void
    {
        $trip = $this->trip([
            'seat_price_cents' => 1600,
            'description' => 'Contribución $16',
            'has_potential_excess_contribution' => true,
            'exceso_contribucion_status' => TripExcessContributionStatus::EN_PROCESO,
        ]);

        $trip = $this->apply($trip, new ContributionCheckResult(16.0, false, false));

        $this->assertTrue($trip->has_potential_excess_contribution);
        $this->assertSame(16.0, $trip->suspected_contribution);
        $this->assertNull($trip->description_potential_seat_price_cents);
        $this->assertSame(TripExcessContributionStatus::EN_PROCESO, $trip->exceso_contribucion_status);
    }

    public function test_implausibly_low_suspected_amount_flags_without_inventing_potential_price(): void
    {
        $trip = $this->apply($this->trip(), new ContributionCheckResult(16.0, false, false));

        $this->assertTrue($trip->has_potential_excess_contribution);
        $this->assertSame(16.0, $trip->suspected_contribution);
        $this->assertNull($trip->description_potential_seat_price_cents);
        $this->assertNull($trip->excess_contribution_percentage);
        $this->assertSame(TripExcessContributionStatus::PENDIENTE, $trip->exceso_contribucion_status);
    }

    public function test_one_peso_or_two_thousand_are_not_implausibly_low(): void
    {
        $onePeso = $this->apply(
            $this->trip(['seat_price_cents' => 100, 'description' => 'Aporte minimo']),
            new ContributionCheckResult(1.0, false, false)
        );
        $this->assertFalse($onePeso->has_potential_excess_contribution);

        $twoThousand = $this->apply(
            $this->trip(['seat_price_cents' => 200000, 'description' => 'Contribución $2000']),
            new ContributionCheckResult(2000.0, false, false)
        );
        $this->assertFalse($twoThousand->has_potential_excess_contribution);
    }

    public function test_excess_percentage_is_never_negative(): void
    {
        $trip = $this->apply(
            $this->trip(['recommended_trip_price_cents' => 15000000]),
            new ContributionCheckResult(24000.0, true, false)
        );

        $this->assertSame(3000000, $trip->average_contribution_cents);
        $this->assertSame(0, $trip->excess_contribution_percentage);
    }
}
