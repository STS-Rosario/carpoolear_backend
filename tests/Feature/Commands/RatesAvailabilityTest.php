<?php

namespace Tests\Feature\Commands;

use Tests\TestCase;
use STS\Models\User;
use STS\Models\Trip;
use STS\Models\Rating;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class RatesAvailabilityTest extends TestCase
{
    use DatabaseTransactions;

    public function testMakesOldVotedRatingsAvailable()
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $trip = Trip::factory()->create(['user_id' => $userA->id]);

        $rating = Rating::factory()->create([
            'trip_id' => $trip->id,
            'user_id_from' => $userA->id,
            'user_id_to' => $userB->id,
            'voted' => 1,
            'available' => 0,
            'created_at' => Carbon::now()->subDays(Rating::RATING_INTERVAL + 1),
        ]);

        $this->artisan('rating:availables')->assertSuccessful();

        $rating->refresh();
        $this->assertEquals(1, $rating->available);
    }

    public function testDoesNotMakeRecentOneWayRatingAvailable()
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $trip = Trip::factory()->create(['user_id' => $userA->id]);

        // Recent rating, only one side voted
        $rating = Rating::factory()->create([
            'trip_id' => $trip->id,
            'user_id_from' => $userA->id,
            'user_id_to' => $userB->id,
            'voted' => 1,
            'available' => 0,
            'created_at' => Carbon::now()->subDays(5),
        ]);

        $this->artisan('rating:availables')->assertSuccessful();

        $rating->refresh();
        $this->assertEquals(0, $rating->available);
    }

    public function testMakesMutualRecentRatingsAvailable()
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $trip = Trip::factory()->create(['user_id' => $userA->id]);

        $recentDate = Carbon::now()->subDays(2);

        // A rates B
        $ratingAtoB = Rating::factory()->create([
            'trip_id' => $trip->id,
            'user_id_from' => $userA->id,
            'user_id_to' => $userB->id,
            'voted' => 1,
            'available' => 0,
            'created_at' => $recentDate,
        ]);

        // B rates A (mutual)
        $ratingBtoA = Rating::factory()->create([
            'trip_id' => $trip->id,
            'user_id_from' => $userB->id,
            'user_id_to' => $userA->id,
            'voted' => 1,
            'available' => 0,
            'created_at' => $recentDate,
        ]);

        $this->artisan('rating:availables')->assertSuccessful();

        $ratingAtoB->refresh();
        $ratingBtoA->refresh();
        $this->assertEquals(1, $ratingAtoB->available);
        $this->assertEquals(1, $ratingBtoA->available);
    }

    public function testDoesNotAffectUnvotedRatings()
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $trip = Trip::factory()->create(['user_id' => $userA->id]);

        $rating = Rating::factory()->create([
            'trip_id' => $trip->id,
            'user_id_from' => $userA->id,
            'user_id_to' => $userB->id,
            'voted' => 0,
            'available' => 0,
            'created_at' => Carbon::now()->subDays(Rating::RATING_INTERVAL + 1),
        ]);

        $this->artisan('rating:availables')->assertSuccessful();

        $rating->refresh();
        $this->assertEquals(0, $rating->available);
    }
}
