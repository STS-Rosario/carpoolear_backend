<?php

namespace STS\Services\UserMigrationUndo;

use Illuminate\Support\Facades\DB;

class MigratedRecordRestorer
{
    /**
     * @var list<array{table: string, column: string}>
     */
    private const TABLE_COLUMNS = [
        ['table' => 'trips', 'column' => 'user_id'],
        ['table' => 'trip_passengers', 'column' => 'user_id'],
        ['table' => 'rating', 'column' => 'user_id_from'],
        ['table' => 'rating', 'column' => 'user_id_to'],
        ['table' => 'users_references', 'column' => 'user_id_from'],
        ['table' => 'users_references', 'column' => 'user_id_to'],
    ];

    /**
     * @return array<string, int>
     */
    public function restore(int $keptId, int $removedId, bool $dryRun): array
    {
        $counts = [];

        foreach (self::TABLE_COLUMNS as $definition) {
            $key = $definition['table'].'.'.$definition['column'];
            $counts[$key] = $this->restoreColumn(
                $definition['table'],
                $definition['column'],
                $keptId,
                $removedId,
                $dryRun
            );
        }

        return $counts;
    }

    private function restoreColumn(string $table, string $column, int $keptId, int $removedId, bool $dryRun): int
    {
        $ids = $this->rowsToReassign($table, $column, $keptId, $removedId);

        if ($ids === []) {
            return 0;
        }

        if (! $dryRun) {
            DB::table($table)
                ->whereIn('id', $ids)
                ->update([$column => $removedId]);
        }

        return count($ids);
    }

    /**
     * @return list<int>
     */
    public function rowsToReassign(string $table, string $column, int $keptId, int $removedId): array
    {
        $prod = DB::table($table)
            ->where($column, $keptId)
            ->pluck('id')
            ->all();

        if ($prod === []) {
            return [];
        }

        $backupIds = DB::connection('backup_db')
            ->table($table)
            ->whereIn('id', $prod)
            ->where($column, $removedId)
            ->pluck('id')
            ->all();

        return array_values(array_map('intval', $backupIds));
    }
}
