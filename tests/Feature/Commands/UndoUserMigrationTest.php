<?php

namespace Tests\Feature\Commands;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use STS\Console\Commands\UndoUserMigration;
use STS\Models\MaintenanceState;
use STS\Models\Trip;
use STS\Models\User;
use STS\Models\UserMigration;
use STS\Services\AnonymizationService;
use STS\Services\Maintenance\MaintenanceStateService;
use STS\Services\UserDeletionService;
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

    public function test_live_run_reassigns_trips_after_reinserting_hard_deleted_removed_user(): void
    {
        $kept = User::factory()->create();
        $removed = User::factory()->create(['email' => 'removed-trip@example.test']);
        $removedId = $removed->id;
        $trip = Trip::factory()->create(['user_id' => $kept->id]);

        $this->copyTablesToBackup(['users', 'trips']);
        DB::connection('backup_db')->table('trips')
            ->where('id', $trip->id)
            ->update(['user_id' => $removedId]);

        app(UserDeletionService::class)->deleteUser($removed);
        $this->assertDatabaseMissing('users', ['id' => $removedId]);

        $this->artisan('user:undo-migration', [
            'kept' => $kept->id,
            'removed' => $removedId,
            '--force' => true,
        ])->assertSuccessful();

        $this->assertDatabaseHas('users', ['id' => $removedId, 'email' => 'removed-trip@example.test']);
        $this->assertSame($removedId, (int) $trip->fresh()->user_id);
    }

    public function test_live_run_restores_migrated_trip_ownership_from_backup(): void
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
            '--force' => true,
        ])->assertSuccessful();

        $this->assertSame($removed->id, (int) $trip->fresh()->user_id);
    }

    public function test_live_run_restores_anonymized_removed_user_from_backup(): void
    {
        $kept = User::factory()->create();
        $removed = User::factory()->create([
            'name' => 'Removed User',
            'email' => 'removed@example.test',
            'active' => true,
        ]);

        $this->copyTablesToBackup(['users']);
        app(AnonymizationService::class)->anonymize($removed);

        $this->artisan('user:undo-migration', [
            'kept' => $kept->id,
            'removed' => $removed->id,
            '--force' => true,
        ])->assertSuccessful();

        $this->assertDatabaseHas('users', [
            'id' => $removed->id,
            'name' => 'Removed User',
            'email' => 'removed@example.test',
            'active' => 1,
        ]);
    }

    public function test_live_run_reinserts_hard_deleted_removed_user_from_backup(): void
    {
        $kept = User::factory()->create();
        $removed = User::factory()->create([
            'email' => 'deleted@example.test',
            'active' => true,
        ]);
        $removedId = $removed->id;

        $this->copyTablesToBackup(['users']);
        app(UserDeletionService::class)->deleteUser($removed);

        $this->assertDatabaseMissing('users', ['id' => $removedId]);

        $this->artisan('user:undo-migration', [
            'kept' => $kept->id,
            'removed' => $removedId,
            '--force' => true,
        ])->assertSuccessful();

        $this->assertDatabaseHas('users', [
            'id' => $removedId,
            'email' => 'deleted@example.test',
            'active' => 1,
        ]);
    }

    public function test_live_run_restores_kept_user_profile_from_backup(): void
    {
        $kept = User::factory()->create([
            'email' => 'merged@example.test',
            'nro_doc' => '99999999',
            'mobile_phone' => '+5499999999999',
        ]);
        $removed = User::factory()->create();

        $this->copyTablesToBackup(['users']);
        DB::connection('backup_db')->table('users')
            ->where('id', $kept->id)
            ->update([
                'email' => 'original-kept@example.test',
                'nro_doc' => '11111111',
                'mobile_phone' => '+5491111111111',
            ]);

        $this->artisan('user:undo-migration', [
            'kept' => $kept->id,
            'removed' => $removed->id,
            '--force' => true,
        ])->assertSuccessful();

        $kept->refresh();
        $this->assertSame('original-kept@example.test', $kept->email);
        $this->assertSame('11111111', $kept->nro_doc);
        $this->assertSame('+5491111111111', $kept->mobile_phone);
    }

    public function test_live_run_restores_parent_trip_before_child_trip_for_conversation(): void
    {
        $kept = User::factory()->create();
        $removed = User::factory()->create(['email' => 'removed-parent-trip@example.test']);
        $removedId = $removed->id;
        $parentTrip = Trip::factory()->create(['user_id' => $removedId]);
        $childTrip = Trip::factory()->create([
            'user_id' => $removedId,
            'parent_trip_id' => $parentTrip->id,
        ]);

        $conversationId = DB::table('conversations')->insertGetId([
            'type' => 0,
            'title' => 'Parent/child trip conversation',
            'trip_id' => $childTrip->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('conversations_users')->insert([
            'conversation_id' => $conversationId,
            'user_id' => $removedId,
            'read' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->copyTablesToBackup(['users', 'trips', 'conversations', 'conversations_users']);
        DB::table('conversations_users')->where('user_id', $removedId)->delete();
        DB::table('conversations')->where('id', $conversationId)->delete();
        DB::table('trips')->whereIn('id', [$parentTrip->id, $childTrip->id])->delete();
        app(UserDeletionService::class)->deleteUser($removed);

        $this->artisan('user:undo-migration', [
            'kept' => $kept->id,
            'removed' => $removedId,
            '--force' => true,
        ])->assertSuccessful();

        $this->assertDatabaseHas('trips', ['id' => $parentTrip->id, 'user_id' => $removedId]);
        $this->assertDatabaseHas('trips', [
            'id' => $childTrip->id,
            'user_id' => $removedId,
            'parent_trip_id' => $parentTrip->id,
        ]);
        $this->assertDatabaseHas('conversations', ['id' => $conversationId, 'trip_id' => $childTrip->id]);
    }

    public function test_live_run_restores_trips_before_conversations_with_trip_id(): void
    {
        $kept = User::factory()->create();
        $removed = User::factory()->create(['email' => 'removed-trip-conv@example.test']);
        $removedId = $removed->id;
        $trip = Trip::factory()->create(['user_id' => $removedId]);

        $conversationId = DB::table('conversations')->insertGetId([
            'type' => 0,
            'title' => 'Trip conversation',
            'trip_id' => $trip->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('conversations_users')->insert([
            'conversation_id' => $conversationId,
            'user_id' => $removedId,
            'read' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->copyTablesToBackup(['users', 'trips', 'conversations', 'conversations_users']);
        DB::table('conversations_users')->where('user_id', $removedId)->delete();
        DB::table('conversations')->where('id', $conversationId)->delete();
        DB::table('trips')->where('id', $trip->id)->delete();
        app(UserDeletionService::class)->deleteUser($removed);

        $this->artisan('user:undo-migration', [
            'kept' => $kept->id,
            'removed' => $removedId,
            '--force' => true,
        ])->assertSuccessful();

        $this->assertDatabaseHas('trips', ['id' => $trip->id, 'user_id' => $removedId]);
        $this->assertDatabaseHas('conversations', ['id' => $conversationId, 'trip_id' => $trip->id]);
        $this->assertDatabaseHas('conversations_users', [
            'conversation_id' => $conversationId,
            'user_id' => $removedId,
        ]);
    }

    public function test_live_run_restores_conversations_before_conversations_users_pivot(): void
    {
        $kept = User::factory()->create();
        $removed = User::factory()->create(['email' => 'removed-conv@example.test']);
        $removedId = $removed->id;

        $conversationId = DB::table('conversations')->insertGetId([
            'type' => 0,
            'title' => 'Undo test conversation',
            'trip_id' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('conversations_users')->insert([
            'conversation_id' => $conversationId,
            'user_id' => $removedId,
            'read' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->copyTablesToBackup(['users', 'conversations', 'conversations_users']);
        DB::table('conversations_users')->where('user_id', $removedId)->delete();
        DB::table('conversations')->where('id', $conversationId)->delete();
        app(UserDeletionService::class)->deleteUser($removed);

        $this->assertDatabaseMissing('conversations', ['id' => $conversationId]);
        $this->assertDatabaseMissing('users', ['id' => $removedId]);

        $this->artisan('user:undo-migration', [
            'kept' => $kept->id,
            'removed' => $removedId,
            '--force' => true,
        ])->assertSuccessful();

        $this->assertDatabaseHas('conversations', ['id' => $conversationId, 'title' => 'Undo test conversation']);
        $this->assertDatabaseHas('conversations_users', [
            'conversation_id' => $conversationId,
            'user_id' => $removedId,
        ]);
    }

    public function test_dry_run_handles_conversations_users_without_id_column(): void
    {
        $kept = User::factory()->create();
        $removed = User::factory()->create();
        $conversationId = DB::table('conversations')->insertGetId([
            'title' => 'Test conversation',
            'type' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('conversations_users')->insert([
            'conversation_id' => $conversationId,
            'user_id' => $removed->id,
            'read' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->copyTablesToBackup(['users', 'conversations', 'conversations_users']);
        DB::table('conversations_users')->where('user_id', $removed->id)->delete();
        app(UserDeletionService::class)->deleteUser($removed);

        $this->artisan('user:undo-migration', [
            'kept' => $kept->id,
            'removed' => $removed->id,
            '--dry-run' => true,
            '--force' => true,
        ])
            ->expectsOutputToContain('Dry run complete')
            ->assertSuccessful();
    }

    public function test_live_run_restores_cascade_deleted_related_data_from_backup(): void
    {
        $kept = User::factory()->create();
        $friend = User::factory()->create();
        $removed = User::factory()->create(['email' => 'removed-related@example.test']);

        DB::table('users_devices')->insert([
            'user_id' => $removed->id,
            'session_id' => 'session-1',
            'device_id' => 'device-1',
            'device_type' => 'android',
            'app_version' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('social_accounts')->insert([
            'user_id' => $removed->id,
            'provider_user_id' => 'fb-123',
            'provider' => 'facebook',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('friends')->insert([
            'uid1' => $removed->id,
            'uid2' => $friend->id,
            'origin' => 'system',
            'state' => User::FRIEND_ACCEPTED,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->copyTablesToBackup(['users', 'users_devices', 'social_accounts', 'friends']);
        app(UserDeletionService::class)->deleteUser($removed);

        $this->assertDatabaseMissing('users', ['id' => $removed->id]);
        $this->assertDatabaseMissing('users_devices', ['user_id' => $removed->id]);

        $this->artisan('user:undo-migration', [
            'kept' => $kept->id,
            'removed' => $removed->id,
            '--force' => true,
        ])->assertSuccessful();

        $this->assertDatabaseHas('users', ['id' => $removed->id, 'email' => 'removed-related@example.test']);
        $this->assertDatabaseHas('users_devices', ['user_id' => $removed->id, 'device_id' => 'device-1']);
        $this->assertDatabaseHas('social_accounts', ['user_id' => $removed->id, 'provider_user_id' => 'fb-123']);
        $this->assertDatabaseHas('friends', ['uid1' => $removed->id, 'uid2' => $friend->id]);
    }

    public function test_live_run_enables_and_disables_maintenance_when_inactive(): void
    {
        $kept = User::factory()->create();
        $removed = User::factory()->create();

        $this->copyTablesToBackup(['users']);
        $this->assertFalse(MaintenanceState::query()->findOrFail(1)->is_active);

        $this->artisan('user:undo-migration', [
            'kept' => $kept->id,
            'removed' => $removed->id,
            '--force' => true,
        ])->assertSuccessful();

        $state = MaintenanceState::query()->findOrFail(1);
        $this->assertFalse($state->is_active);
    }

    public function test_live_run_leaves_existing_maintenance_active(): void
    {
        $kept = User::factory()->create();
        $removed = User::factory()->create();

        app(MaintenanceStateService::class)->applyManualActive(
            true,
            'strict',
            'Existing outage',
            null,
            'manual',
            null,
            null
        );

        $this->copyTablesToBackup(['users']);

        $this->artisan('user:undo-migration', [
            'kept' => $kept->id,
            'removed' => $removed->id,
            '--force' => true,
        ])->assertSuccessful();

        $state = MaintenanceState::query()->findOrFail(1);
        $this->assertTrue($state->is_active);
        $this->assertSame('Existing outage', $state->message);
    }

    public function test_aborts_when_confirmation_is_declined(): void
    {
        $kept = User::factory()->create();
        $removed = User::factory()->create(['name' => 'Before Undo']);

        $this->copyTablesToBackup(['users']);

        $this->artisan('user:undo-migration', [
            'kept' => $kept->id,
            'removed' => $removed->id,
        ])
            ->expectsConfirmation('Proceed with undo migration?', 'no')
            ->expectsOutputToContain('Aborted.')
            ->assertSuccessful();

        $this->assertDatabaseHas('users', ['id' => $removed->id, 'name' => 'Before Undo']);
    }

    public function test_full_undo_removes_migration_audit_row_and_restores_migrated_data(): void
    {
        $kept = User::factory()->create([
            'email' => 'new-kept@example.test',
            'password' => Hash::make('new-password'),
            'nro_doc' => '22222222',
        ]);
        $removed = User::factory()->create([
            'email' => 'old-removed@example.test',
            'password' => Hash::make('old-password'),
            'nro_doc' => '11111111',
            'active' => true,
        ]);
        $trip = Trip::factory()->create(['user_id' => $kept->id]);

        $this->copyTablesToBackup(['users', 'trips']);
        DB::connection('backup_db')->table('trips')
            ->where('id', $trip->id)
            ->update(['user_id' => $removed->id]);
        DB::connection('backup_db')->table('users')
            ->where('id', $kept->id)
            ->update(['email' => 'kept-before@example.test', 'nro_doc' => '33333333']);

        app(AnonymizationService::class)->anonymize($removed);

        UserMigration::query()->create([
            'admin_user_id' => $kept->id,
            'user_id_kept' => $kept->id,
            'user_id_removed' => $removed->id,
        ]);

        $this->artisan('user:undo-migration', [
            'kept' => $kept->id,
            'removed' => $removed->id,
            '--force' => true,
        ])->assertSuccessful();

        $this->assertSame($removed->id, (int) $trip->fresh()->user_id);
        $this->assertDatabaseHas('users', [
            'id' => $removed->id,
            'email' => 'old-removed@example.test',
            'active' => 1,
        ]);
        $this->assertDatabaseHas('users', [
            'id' => $kept->id,
            'email' => 'kept-before@example.test',
            'nro_doc' => '33333333',
        ]);
        $this->assertDatabaseMissing('user_migrations', [
            'user_id_kept' => $kept->id,
            'user_id_removed' => $removed->id,
        ]);
    }
}
