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
}
