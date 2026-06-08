<?php

namespace Tests\Unit\Models;

use STS\Models\Trip;
use STS\Models\TripLiveShare;
use STS\Models\User;
use Tests\TestCase;

class TripLiveShareTest extends TestCase
{
    public function test_factory_creates_share_with_token_and_nullable_coordinates(): void
    {
        $share = TripLiveShare::factory()->create([
            'lat' => null,
            'lng' => null,
        ]);

        $this->assertNotEmpty($share->share_token);
        $this->assertGreaterThanOrEqual(48, strlen($share->share_token));
        $this->assertNull($share->lat);
        $this->assertNull($share->lng);
        $this->assertTrue($share->is_active);
    }

    public function test_belongs_to_trip_and_user(): void
    {
        $user = User::factory()->create();
        $trip = Trip::factory()->create(['user_id' => $user->id]);
        $share = TripLiveShare::factory()->create([
            'trip_id' => $trip->id,
            'user_id' => $user->id,
        ]);

        $this->assertTrue($share->trip->is($trip));
        $this->assertTrue($share->user->is($user));
    }
}
