<?php

namespace Tests\Unit\Models;

use STS\Models\Passenger;
use STS\Models\Rating;
use STS\Models\Trip;
use STS\Models\TripContributionOverchargeReport;
use STS\Models\User;
use Tests\TestCase;

class TripContributionOverchargeReportTest extends TestCase
{
    public function test_persists_who_answered_and_whether_they_paid_more(): void
    {
        $user = User::factory()->create();
        $trip = Trip::factory()->create();

        $report = TripContributionOverchargeReport::query()->create([
            'user_id' => $user->id,
            'trip_id' => $trip->id,
            'paid_more' => false,
        ]);

        $this->assertFalse($report->fresh()->paid_more);
        $this->assertSame($user->id, $report->user_id);
        $this->assertSame($trip->id, $report->trip_id);
        $this->assertNotNull($report->created_at);
    }

    public function test_should_ask_only_when_a_passenger_rates_a_driver_with_a_positive_contribution(): void
    {
        $trip = new Trip(['seat_price_cents' => 1500000]);
        $ratingDriver = new Rating(['user_to_type' => Passenger::TYPE_CONDUCTOR]);
        $ratingPassenger = new Rating(['user_to_type' => Passenger::TYPE_PASAJERO]);

        $this->assertTrue(TripContributionOverchargeReport::shouldAsk($ratingDriver, $trip));
        $this->assertFalse(TripContributionOverchargeReport::shouldAsk($ratingPassenger, $trip));
        $this->assertFalse(TripContributionOverchargeReport::shouldAsk(
            $ratingDriver,
            new Trip(['seat_price_cents' => 0])
        ));
        $this->assertFalse(TripContributionOverchargeReport::shouldAsk(
            $ratingDriver,
            new Trip(['seat_price_cents' => -1])
        ));
        $this->assertFalse(TripContributionOverchargeReport::shouldAsk(
            $ratingDriver,
            new Trip(['seat_price_cents' => null])
        ));
    }
}
