<?php

namespace Tests\Feature\Commands;

use Tests\TestCase;
use STS\Models\User;
use STS\Models\Trip;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class CleanTripVisibilityTest extends TestCase
{
    use DatabaseTransactions;

    public function testDeletesVisibilityForPastTrips()
    {
        $user = User::factory()->create();

        // Future private trip (should keep its visibility)
        $futureTrip = Trip::factory()->create([
            'user_id' => $user->id,
            'trip_date' => Carbon::now()->addDays(3),
            'friendship_type_id' => Trip::PRIVACY_FRIENDS,
        ]);

        // Past private trip (visibility should be cleaned)
        $pastTrip = Trip::factory()->create([
            'user_id' => $user->id,
            'trip_date' => Carbon::now()->subDays(3),
            'friendship_type_id' => Trip::PRIVACY_FRIENDS,
        ]);

        DB::table('user_visibility_trip')->insert([
            ['user_id' => $user->id, 'trip_id' => $futureTrip->id],
            ['user_id' => $user->id, 'trip_id' => $pastTrip->id],
        ]);

        $this->artisan('trip:visibilityclean')->assertSuccessful();

        // Future private trip visibility kept
        $this->assertDatabaseHas('user_visibility_trip', [
            'user_id' => $user->id,
            'trip_id' => $futureTrip->id,
        ]);

        // Past trip visibility cleaned
        $this->assertDatabaseMissing('user_visibility_trip', [
            'user_id' => $user->id,
            'trip_id' => $pastTrip->id,
        ]);
    }

    public function testDeletesVisibilityForPublicTrips()
    {
        $user = User::factory()->create();

        // Future public trip (visibility not needed, should be cleaned)
        $publicTrip = Trip::factory()->create([
            'user_id' => $user->id,
            'trip_date' => Carbon::now()->addDays(3),
            'friendship_type_id' => Trip::PRIVACY_PUBLIC,
        ]);

        // Future private trip (should keep)
        $privateTrip = Trip::factory()->create([
            'user_id' => $user->id,
            'trip_date' => Carbon::now()->addDays(3),
            'friendship_type_id' => Trip::PRIVACY_FRIENDS,
        ]);

        DB::table('user_visibility_trip')->insert([
            ['user_id' => $user->id, 'trip_id' => $publicTrip->id],
            ['user_id' => $user->id, 'trip_id' => $privateTrip->id],
        ]);

        $this->artisan('trip:visibilityclean')->assertSuccessful();

        // Public trip visibility cleaned (public trips are not in the "keep" set)
        $this->assertDatabaseMissing('user_visibility_trip', [
            'user_id' => $user->id,
            'trip_id' => $publicTrip->id,
        ]);

        // Private trip visibility kept
        $this->assertDatabaseHas('user_visibility_trip', [
            'user_id' => $user->id,
            'trip_id' => $privateTrip->id,
        ]);
    }

    public function testRunsSuccessfullyWithNoVisibilityRecords()
    {
        $this->artisan('trip:visibilityclean')->assertSuccessful();
    }
}
