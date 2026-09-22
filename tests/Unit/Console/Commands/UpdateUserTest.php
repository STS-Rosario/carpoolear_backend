<?php

namespace Tests\Unit\Console\Commands;

use Carbon\Carbon;
use Illuminate\Support\Facades\Artisan;
use STS\Models\Passenger;
use STS\Models\Rating;
use STS\Models\Trip;
use STS\Models\User;
use Tests\TestCase;

class UpdateUserTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2028-06-15 12:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_updates_trips_from_original_to_new_user(): void
    {
        $originalUser = User::factory()->create();
        $newUser = User::factory()->create();

        $trip = Trip::factory()->create(['user_id' => $originalUser->id]);

        Artisan::call('user:update', [
            'original' => $originalUser->id,
            'new' => $newUser->id,
        ]);

        $trip->refresh();
        $this->assertEquals($newUser->id, $trip->user_id);
    }

    public function test_updates_passenger_records_from_original_to_new_user(): void
    {
        $originalUser = User::factory()->create();
        $newUser = User::factory()->create();
        $driver = User::factory()->create();

        $trip = Trip::factory()->create(['user_id' => $driver->id]);
        $passenger = Passenger::factory()->create([
            'trip_id' => $trip->id,
            'user_id' => $originalUser->id,
            'request_state' => Passenger::STATE_ACCEPTED,
        ]);

        Artisan::call('user:update', [
            'original' => $originalUser->id,
            'new' => $newUser->id,
        ]);

        $passenger->refresh();
        $this->assertEquals($newUser->id, $passenger->user_id);
    }

    public function test_updates_ratings_from_original_to_new_user(): void
    {
        $originalUser = User::factory()->create();
        $newUser = User::factory()->create();
        $otherUser = User::factory()->create();

        $trip = Trip::factory()->create(['user_id' => $otherUser->id]);

        $ratingFrom = Rating::factory()->create([
            'trip_id' => $trip->id,
            'user_id_from' => $originalUser->id,
            'user_id_to' => $otherUser->id,
        ]);

        $ratingTo = Rating::factory()->create([
            'trip_id' => $trip->id,
            'user_id_from' => $otherUser->id,
            'user_id_to' => $originalUser->id,
        ]);

        Artisan::call('user:update', [
            'original' => $originalUser->id,
            'new' => $newUser->id,
        ]);

        $ratingFrom->refresh();
        $ratingTo->refresh();

        $this->assertEquals($newUser->id, $ratingFrom->user_id_from);
        $this->assertEquals($newUser->id, $ratingTo->user_id_to);
    }

    public function test_refreshes_trips_count_for_surviving_user(): void
    {
        $originalUser = User::factory()->create(['trips_count' => null]);
        $newUser = User::factory()->create(['trips_count' => null]);

        // Create a finished trip for the original user
        $trip = Trip::factory()->create([
            'user_id' => $originalUser->id,
            'trip_date' => Carbon::now()->subDay(),
        ]);

        Artisan::call('user:update', [
            'original' => $originalUser->id,
            'new' => $newUser->id,
        ]);

        $newUser->refresh();
        $this->assertNotNull($newUser->trips_count);
        $this->assertEquals(1, $newUser->trips_count);
    }

    public function test_refreshes_trips_count_includes_merged_passenger_trips(): void
    {
        $originalUser = User::factory()->create(['trips_count' => null]);
        $newUser = User::factory()->create(['trips_count' => null]);
        $driver = User::factory()->create();

        // Create a finished trip where originalUser was an accepted passenger
        $trip = Trip::factory()->create([
            'user_id' => $driver->id,
            'trip_date' => Carbon::now()->subDay(),
        ]);

        Passenger::factory()->create([
            'trip_id' => $trip->id,
            'user_id' => $originalUser->id,
            'request_state' => Passenger::STATE_ACCEPTED,
        ]);

        Artisan::call('user:update', [
            'original' => $originalUser->id,
            'new' => $newUser->id,
        ]);

        $newUser->refresh();
        $this->assertNotNull($newUser->trips_count);
        $this->assertEquals(1, $newUser->trips_count);
    }

    public function test_refreshes_trips_count_with_combined_trips(): void
    {
        $originalUser = User::factory()->create(['trips_count' => null]);
        $newUser = User::factory()->create(['trips_count' => null]);

        // Create finished trips for both users
        Trip::factory()->create([
            'user_id' => $originalUser->id,
            'trip_date' => Carbon::now()->subDay(),
        ]);

        Trip::factory()->create([
            'user_id' => $newUser->id,
            'trip_date' => Carbon::now()->subDays(2),
        ]);

        Artisan::call('user:update', [
            'original' => $originalUser->id,
            'new' => $newUser->id,
        ]);

        $newUser->refresh();
        $this->assertNotNull($newUser->trips_count);
        $this->assertEquals(2, $newUser->trips_count);
    }
}
