<?php

namespace STS\Services\UserMigrationUndo;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use STS\Models\User;
use STS\Models\UserMigration;

class UserMigrationUndoService
{
    public function __construct(
        private readonly MigratedRecordRestorer $migratedRecordRestorer,
        private readonly UserRecordRestorer $userRecordRestorer,
        private readonly UserRelatedDataRestorer $userRelatedDataRestorer,
        private readonly MaintenanceGuard $maintenanceGuard,
    ) {}

    public function undo(int $keptId, int $removedId, bool $dryRun): UndoMigrationResult
    {
        $this->validateInputs($keptId, $removedId);

        if (! $dryRun) {
            $this->maintenanceGuard->enableIfInactive();
        }

        try {
            return $this->runUndo($keptId, $removedId, $dryRun);
        } finally {
            if (! $dryRun) {
                $this->maintenanceGuard->disableIfEnabledByCommand();
            }
        }
    }

    private function runUndo(int $keptId, int $removedId, bool $dryRun): UndoMigrationResult
    {
        $runner = function () use ($keptId, $removedId, $dryRun): UndoMigrationResult {
            // Restore users before FK updates: removed user must exist before reassigning trips, etc.
            $userCounts = $this->userRecordRestorer->restore($keptId, $removedId, $dryRun);
            $migrated = $this->migratedRecordRestorer->restore($keptId, $removedId, $dryRun);
            $related = $this->userRelatedDataRestorer->restore($removedId, $dryRun);
            $migrationRowsDeleted = $this->deleteMigrationRow($keptId, $removedId, $dryRun);

            return new UndoMigrationResult(
                dryRun: $dryRun,
                usersRestored: $userCounts['restored'],
                usersUpdated: $userCounts['updated'],
                migratedReassignments: $migrated,
                relatedRestored: $related,
                migrationRowsDeleted: $migrationRowsDeleted,
            );
        };

        if ($dryRun) {
            return $runner();
        }

        return DB::transaction($runner);
    }

    public function validateInputs(int $keptId, int $removedId): void
    {
        if ($keptId === $removedId) {
            throw new InvalidArgumentException('Kept and removed user IDs must be different.');
        }

        if (! User::query()->whereKey($keptId)->exists()) {
            throw new InvalidArgumentException("Kept user [{$keptId}] does not exist on production.");
        }

        if (! DB::connection('backup_db')->table('users')->where('id', $removedId)->exists()) {
            throw new InvalidArgumentException("Removed user [{$removedId}] does not exist in backup database.");
        }
    }

    private function deleteMigrationRow(int $keptId, int $removedId, bool $dryRun): int
    {
        $exists = UserMigration::query()
            ->where('user_id_kept', $keptId)
            ->where('user_id_removed', $removedId)
            ->exists();

        if (! $exists) {
            return 0;
        }

        if (! $dryRun) {
            UserMigration::query()
                ->where('user_id_kept', $keptId)
                ->where('user_id_removed', $removedId)
                ->delete();
        }

        return 1;
    }
}
