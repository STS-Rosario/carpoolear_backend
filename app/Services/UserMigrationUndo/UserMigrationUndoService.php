<?php

namespace STS\Services\UserMigrationUndo;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use STS\Models\User;

class UserMigrationUndoService
{
    public function __construct(
        private readonly MigratedRecordRestorer $migratedRecordRestorer,
    ) {}

    public function undo(int $keptId, int $removedId, bool $dryRun): UndoMigrationResult
    {
        $this->validateInputs($keptId, $removedId);

        $migrated = $this->migratedRecordRestorer->restore($keptId, $removedId, $dryRun);

        return new UndoMigrationResult(
            dryRun: $dryRun,
            migratedReassignments: $migrated,
        );
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
}
