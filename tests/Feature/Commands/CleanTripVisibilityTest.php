<?php

namespace Tests\Feature\Commands;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use STS\Models\Trip;
use STS\Models\User;
use Tests\TestCase;

class CleanTripVisibilityTest extends TestCase
{
    public function test_deletes_visibility_for_past_trips()
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

    public function test_deletes_visibility_for_public_trips()
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

    public function test_runs_successfully_with_no_visibility_records()
    {
        $this->artisan('trip:visibilityclean')->assertSuccessful();
    }
}
