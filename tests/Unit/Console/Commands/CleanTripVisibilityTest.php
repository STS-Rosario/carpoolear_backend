<?php

namespace Tests\Unit\Console\Commands;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use STS\Console\Commands\CleanTripVisibility;
use STS\Models\Trip;
use STS\Models\User;
use STS\Repository\TripRepository;
use Tests\TestCase;

class CleanTripVisibilityTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_handle_removes_visibility_rows_for_non_eligible_trips_only(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 4, 28, 10, 0, 0));

        $owner = User::factory()->create();
        $viewer = User::factory()->create();

        $eligibleTrip = Trip::factory()->create([
            'user_id' => $owner->id,
            'trip_date' => Carbon::now()->addDay(),
            'friendship_type_id' => Trip::PRIVACY_FRIENDS,
        ]);
        $ineligibleTrip = Trip::factory()->create([
            'user_id' => $owner->id,
            'trip_date' => Carbon::now()->subDay(),
            'friendship_type_id' => Trip::PRIVACY_FRIENDS,
        ]);

        DB::table('user_visibility_trip')->insert([
            ['user_id' => $viewer->id, 'trip_id' => $eligibleTrip->id],
            ['user_id' => $viewer->id, 'trip_id' => $ineligibleTrip->id],
        ]);

        $command = new CleanTripVisibility($this->app->make(TripRepository::class));
        $command->handle();

        $this->assertSame(
            1,
            DB::table('user_visibility_trip')->where('trip_id', $eligibleTrip->id)->count()
        );
        $this->assertSame(
            0,
            DB::table('user_visibility_trip')->where('trip_id', $ineligibleTrip->id)->count()
        );
    }

    public function test_command_contract_is_defined(): void
    {
        $command = new CleanTripVisibility($this->app->make(TripRepository::class));

        $this->assertSame('trip:visibilityclean', $command->getName());
        $this->assertStringContainsString('Clean trip visibility', $command->getDescription());
    }
}
