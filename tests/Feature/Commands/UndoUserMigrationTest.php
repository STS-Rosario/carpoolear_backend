<?php

namespace Tests\Feature\Commands;

use Illuminate\Support\Facades\DB;
use STS\Console\Commands\UndoUserMigration;
use STS\Models\MaintenanceState;
use STS\Models\Trip;
use STS\Models\User;
use Tests\Support\UsesBackupDatabase;
use Tests\TestCase;

class UndoUserMigrationTest extends TestCase
{
    use UsesBackupDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpBackupDatabase();
    }

    public function test_command_signature_and_description_match_expected_contract(): void
    {
        $command = new UndoUserMigration;

        $this->assertSame('user:undo-migration', $command->getName());
        $this->assertStringContainsString('Undo a user migration', $command->getDescription());
        $this->assertTrue($command->getDefinition()->hasArgument('kept'));
        $this->assertTrue($command->getDefinition()->hasArgument('removed'));
        $this->assertTrue($command->getDefinition()->hasOption('dry-run'));
        $this->assertTrue($command->getDefinition()->hasOption('force'));
    }

    public function test_fails_when_kept_user_missing_on_production(): void
    {
        $removed = User::factory()->create();
        $this->copyTablesToBackup(['users']);

        $this->artisan('user:undo-migration', [
            'kept' => 999_999_998,
            'removed' => $removed->id,
        ])
            ->assertFailed();
    }

    public function test_fails_when_removed_user_missing_in_backup(): void
    {
        $kept = User::factory()->create();
        $removed = User::factory()->create();

        $this->artisan('user:undo-migration', [
            'kept' => $kept->id,
            'removed' => $removed->id,
        ])
            ->assertFailed();
    }

    public function test_dry_run_reports_reassignments_without_writing(): void
    {
        $kept = User::factory()->create();
        $removed = User::factory()->create();
        $trip = Trip::factory()->create(['user_id' => $kept->id]);

        $this->copyTablesToBackup(['users', 'trips']);
        DB::connection('backup_db')->table('trips')
            ->where('id', $trip->id)
            ->update(['user_id' => $removed->id]);

        $this->artisan('user:undo-migration', [
            'kept' => $kept->id,
            'removed' => $removed->id,
            '--dry-run' => true,
            '--force' => true,
        ])
            ->expectsOutputToContain('Dry run complete')
            ->assertSuccessful();

        $this->assertSame($kept->id, (int) $trip->fresh()->user_id);
        $this->assertFalse(MaintenanceState::query()->findOrFail(1)->is_active);
    }
}
