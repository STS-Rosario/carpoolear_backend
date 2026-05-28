<?php

namespace Tests\Unit\Console\Commands;

use Carbon\Carbon;
use STS\Console\Commands\CleanupDuplicateCars;
use STS\Models\Car;
use STS\Models\User;
use Tests\TestCase;

class CleanupDuplicateCarsTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_handle_reports_clean_state_when_no_users_have_duplicate_cars(): void
    {
        User::factory()->create();

        $this->artisan('cars:cleanup-duplicates')
            ->expectsOutput('✅ No users with multiple cars found. Database is clean!')
            ->assertExitCode(0);
    }

    public function test_handle_dry_run_reports_mode_and_clean_state_when_no_duplicates_exist(): void
    {
        $user = User::factory()->create(['name' => 'Driver']);
        Car::factory()->create([
            'user_id' => $user->id,
            'patente' => 'AAA111',
            'created_at' => Carbon::create(2026, 4, 27, 10, 0, 0),
            'updated_at' => Carbon::create(2026, 4, 27, 10, 0, 0),
        ]);

        $this->artisan('cars:cleanup-duplicates', ['--dry-run' => true])
            ->expectsOutputToContain('DRY RUN MODE')
            ->expectsOutput('✅ No users with multiple cars found. Database is clean!')
            ->assertExitCode(0);

        $this->assertSame(1, Car::query()->where('user_id', $user->id)->count());
    }

    public function test_command_signature_description_and_options_are_exposed(): void
    {
        $command = new CleanupDuplicateCars;

        $this->assertSame('cars:cleanup-duplicates', $command->getName());
        $this->assertStringContainsString('Clean up duplicate car entries', $command->getDescription());
        $this->assertTrue($command->getDefinition()->hasOption('dry-run'));
    }

    public function test_handle_keeps_newest_car_deletes_older_duplicates_and_relinks_trips(): void
    {
        $user = User::factory()->create(['name' => 'Driver Duplicate']);

        $oldestCar = Car::factory()->create([
            'user_id' => $user->id,
            'patente' => 'OLD111',
            'created_at' => Carbon::create(2026, 4, 1, 8, 0, 0),
            'updated_at' => Carbon::create(2026, 4, 1, 8, 0, 0),
        ]);
        $middleCar = Car::factory()->create([
            'user_id' => $user->id,
            'patente' => 'MID222',
            'created_at' => Carbon::create(2026, 4, 2, 8, 0, 0),
            'updated_at' => Carbon::create(2026, 4, 2, 8, 0, 0),
        ]);
        $newestCar = Car::factory()->create([
            'user_id' => $user->id,
            'patente' => 'NEW333',
            'created_at' => Carbon::create(2026, 4, 3, 8, 0, 0),
            'updated_at' => Carbon::create(2026, 4, 3, 8, 0, 0),
        ]);

        $tripUsingOldest = \STS\Models\Trip::factory()->create([
            'user_id' => $user->id,
            'car_id' => $oldestCar->id,
        ]);
        $tripUsingMiddle = \STS\Models\Trip::factory()->create([
            'user_id' => $user->id,
            'car_id' => $middleCar->id,
        ]);

        $this->artisan('cars:cleanup-duplicates')
            ->expectsOutputToContain('Starting cleanup of duplicate car entries')
            ->expectsOutputToContain('CLEANUP COMPLETED')
            ->expectsOutputToContain('Kept: 1 cars')
            ->expectsOutputToContain('Deleted: 2 cars')
            ->expectsOutputToContain('Total users affected: 1')
            ->assertExitCode(0);

        $remainingCars = Car::query()->where('user_id', $user->id)->get();
        $this->assertCount(1, $remainingCars);
        $this->assertSame($newestCar->id, $remainingCars->first()->id);

        $this->assertNull(Car::query()->find($oldestCar->id));
        $this->assertNull(Car::query()->find($middleCar->id));
        $this->assertNotNull(Car::withTrashed()->find($oldestCar->id)?->deleted_at);
        $this->assertNotNull(Car::withTrashed()->find($middleCar->id)?->deleted_at);

        $this->assertSame($newestCar->id, $tripUsingOldest->fresh()->car_id);
        $this->assertSame($newestCar->id, $tripUsingMiddle->fresh()->car_id);
    }

    public function test_handle_dry_run_with_duplicates_reports_actions_without_mutating_data(): void
    {
        $user = User::factory()->create(['name' => 'Dry Run Driver']);

        $oldCar = Car::factory()->create([
            'user_id' => $user->id,
            'patente' => 'DRY111',
            'created_at' => Carbon::create(2026, 4, 10, 9, 0, 0),
            'updated_at' => Carbon::create(2026, 4, 10, 9, 0, 0),
        ]);
        $newCar = Car::factory()->create([
            'user_id' => $user->id,
            'patente' => 'DRY222',
            'created_at' => Carbon::create(2026, 4, 11, 9, 0, 0),
            'updated_at' => Carbon::create(2026, 4, 11, 9, 0, 0),
        ]);

        $trip = \STS\Models\Trip::factory()->create([
            'user_id' => $user->id,
            'car_id' => $oldCar->id,
        ]);

        $this->artisan('cars:cleanup-duplicates', ['--dry-run' => true])
            ->expectsOutputToContain('DRY RUN MODE')
            ->expectsOutputToContain('DRY RUN SUMMARY')
            ->expectsOutputToContain('Would keep: 1 cars')
            ->expectsOutputToContain('Would delete: 1 cars')
            ->expectsOutputToContain('Total users affected: 1')
            ->assertExitCode(0);

        $this->assertDatabaseHas('cars', ['id' => $oldCar->id]);
        $this->assertDatabaseHas('cars', ['id' => $newCar->id]);
        $this->assertSame($oldCar->id, $trip->fresh()->car_id);
    }
}
