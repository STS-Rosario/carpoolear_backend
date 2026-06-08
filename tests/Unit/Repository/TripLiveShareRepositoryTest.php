<?php

namespace Tests\Unit\Repository;

use Carbon\Carbon;
use STS\Models\Trip;
use STS\Models\TripLiveShare;
use STS\Models\User;
use STS\Repository\TripLiveShareRepository;
use Tests\TestCase;

class TripLiveShareRepositoryTest extends TestCase
{
    public function test_find_driver_share_returns_inactive_driver_share(): void
    {
        $driver = User::factory()->create();
        $trip = Trip::factory()->create(['user_id' => $driver->id]);
        $share = TripLiveShare::factory()->create([
            'trip_id' => $trip->id,
            'user_id' => $driver->id,
            'is_active' => false,
            'lat' => null,
            'lng' => null,
            'stopped_at' => Carbon::now(),
        ]);

        $found = (new TripLiveShareRepository)->findDriverShare($trip->id);

        $this->assertNotNull($found);
        $this->assertSame($share->id, $found->id);
        $this->assertFalse($found->is_active);
    }

    public function test_find_latest_share_for_trip_returns_most_recent_share(): void
    {
        $driver = User::factory()->create();
        $passenger = User::factory()->create();
        $trip = Trip::factory()->create(['user_id' => $driver->id]);
        TripLiveShare::factory()->create([
            'trip_id' => $trip->id,
            'user_id' => $driver->id,
            'is_active' => false,
            'started_at' => Carbon::parse('2026-06-08 10:00:00'),
        ]);
        $passengerShare = TripLiveShare::factory()->create([
            'trip_id' => $trip->id,
            'user_id' => $passenger->id,
            'is_active' => false,
            'started_at' => Carbon::parse('2026-06-08 12:00:00'),
            'stopped_at' => Carbon::now(),
        ]);

        $found = (new TripLiveShareRepository)->findLatestShareForTrip($trip->id);

        $this->assertNotNull($found);
        $this->assertSame($passengerShare->id, $found->id);
    }
}
