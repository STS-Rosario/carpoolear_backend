<?php

namespace STS\Services\UserMigrationUndo;

class UndoMigrationResult
{
    /**
     * @param  array<string, int>  $migratedReassignments
     * @param  array<string, int>  $relatedRestored
     */
    public function __construct(
        public readonly bool $dryRun,
        public readonly int $usersRestored = 0,
        public readonly int $usersUpdated = 0,
        public readonly array $migratedReassignments = [],
        public readonly array $relatedRestored = [],
        public readonly int $migrationRowsDeleted = 0,
    ) {}

    public function totalMigratedReassignments(): int
    {
        return array_sum($this->migratedReassignments);
    }

    public function totalRelatedRestored(): int
    {
        return array_sum($this->relatedRestored);
    }
}
