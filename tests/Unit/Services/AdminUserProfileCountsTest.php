<?php

namespace Tests\Unit\Services;

use STS\Models\Rating;
use STS\Models\Trip;
use STS\Models\User;
use STS\Services\AdminUserProfileCounts;
use Tests\TestCase;

class AdminUserProfileCountsTest extends TestCase
{
    public function test_ratings_count_sums_received_and_given_ratings(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $trip = Trip::factory()->create(['user_id' => $other->id]);

        Rating::factory()->create([
            'trip_id' => $trip->id,
            'user_id_to' => $user->id,
            'user_id_from' => $other->id,
        ]);
        Rating::factory()->create([
            'trip_id' => $trip->id,
            'user_id_from' => $user->id,
            'user_id_to' => $other->id,
        ]);

        $service = app(AdminUserProfileCounts::class);

        $this->assertSame(2, $service->ratingsCount($user->id));
    }
}
