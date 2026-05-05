<?php

namespace Tests\Unit\Console\Commands;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use STS\Console\Commands\CleanupDuplicateCars;
use STS\Models\Car;
use STS\Models\User;
use Tests\TestCase;

class CleanupDuplicateCarsTest extends TestCase
{
    private bool $droppedCarsUserUniqueIndex = false;

    protected function tearDown(): void
    {
        if ($this->droppedCarsUserUniqueIndex) {
            DB::statement(
                'DELETE c1 FROM cars c1 JOIN cars c2 ON c1.user_id = c2.user_id AND c1.id < c2.id WHERE c1.user_id IS NOT NULL'
            );
            DB::statement('ALTER TABLE cars DROP INDEX cars_user_id_index, ADD UNIQUE INDEX cars_user_id_unique (user_id)');
        }

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
        $this->dropCarsUserUniqueIndex();

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

        $this->assertDatabaseMissing('cars', ['id' => $oldestCar->id]);
        $this->assertDatabaseMissing('cars', ['id' => $middleCar->id]);

        $this->assertSame($newestCar->id, $tripUsingOldest->fresh()->car_id);
        $this->assertSame($newestCar->id, $tripUsingMiddle->fresh()->car_id);
    }

    public function test_handle_dry_run_with_duplicates_reports_actions_without_mutating_data(): void
    {
        $this->dropCarsUserUniqueIndex();

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

    private function dropCarsUserUniqueIndex(): void
    {
        DB::statement('ALTER TABLE cars DROP INDEX cars_user_id_unique, ADD INDEX cars_user_id_index (user_id)');
        $this->droppedCarsUserUniqueIndex = true;
    }
}
