<?php

namespace Tests\Unit\Helpers;

use PHPUnit\Framework\TestCase;
use STS\Helpers\TripDescriptionContributionHelper;
use STS\Models\Trip;

class TripDescriptionContributionHelperSyncTest extends TestCase
{
    public function test_sync_potential_excess_contribution_attributes_sets_flag_and_amount(): void
    {
        $trip = new Trip([
            'seat_price_cents' => 1500000,
            'description' => 'La contribución es de $24000 por persona',
        ]);

        TripDescriptionContributionHelper::syncPotentialExcessContributionAttributes($trip);

        $this->assertTrue($trip->has_potential_excess_contribution);
        $this->assertSame(2400000, $trip->description_potential_seat_price_cents);
    }

    public function test_sync_potential_excess_contribution_attributes_clears_flag_when_not_excess(): void
    {
        $trip = new Trip([
            'seat_price_cents' => 1500000,
            'description' => 'Contribución $15000',
            'has_potential_excess_contribution' => true,
            'description_potential_seat_price_cents' => 2400000,
        ]);

        TripDescriptionContributionHelper::syncPotentialExcessContributionAttributes($trip);

        $this->assertFalse($trip->has_potential_excess_contribution);
        $this->assertNull($trip->description_potential_seat_price_cents);
    }
}
