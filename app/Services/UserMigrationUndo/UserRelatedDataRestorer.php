<?php

namespace STS\Services\UserMigrationUndo;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class UserRelatedDataRestorer
{
    /**
     * @var list<array{table: string, columns: list<string>, compositeKey?: list<string>}>
     */
    private const TABLE_REGISTRY = [
        ['table' => 'users_devices', 'columns' => ['user_id']],
        ['table' => 'social_accounts', 'columns' => ['user_id']],
        ['table' => 'cars', 'columns' => ['user_id']],
        ['table' => 'friends', 'columns' => ['uid1', 'uid2'], 'compositeKey' => ['uid1', 'uid2']],
        ['table' => 'notifications', 'columns' => ['user_id']],
        ['table' => 'subscriptions', 'columns' => ['user_id']],
        ['table' => 'donations', 'columns' => ['user_id']],
        ['table' => 'payments', 'columns' => ['user_id']],
        ['table' => 'conversations_users', 'columns' => ['user_id'], 'compositeKey' => ['conversation_id', 'user_id']],
        ['table' => 'messages', 'columns' => ['user_id']],
        ['table' => 'user_message_read', 'columns' => ['user_id'], 'compositeKey' => ['user_id', 'message_id']],
        ['table' => 'user_badges', 'columns' => ['user_id']],
        ['table' => 'phone_verifications', 'columns' => ['user_id']],
        ['table' => 'support_tickets', 'columns' => ['user_id']],
        ['table' => 'support_ticket_replies', 'columns' => ['user_id']],
        ['table' => 'support_ticket_attachments', 'columns' => ['user_id']],
        ['table' => 'delete_account_requests', 'columns' => ['user_id']],
        ['table' => 'banned_users', 'columns' => ['user_id']],
        ['table' => 'manual_identity_validations', 'columns' => ['user_id']],
        ['table' => 'mercado_pago_rejected_validations', 'columns' => ['user_id']],
        ['table' => 'friend_trip_alert_subscriptions', 'columns' => ['user_id', 'friend_id']],
        ['table' => 'trip_live_shares', 'columns' => ['user_id']],
        ['table' => 'campaign_donations', 'columns' => ['user_id']],
    ];

    /**
     * @return array<string, int>
     */
    public function restore(int $removedId, bool $dryRun): array
    {
        $conversationIds = $this->conversationIdsForRemovedUser($removedId);

        $counts = [
            'trips' => $this->restoreTripParentsForConversations($conversationIds, $dryRun),
            'conversations' => $this->restoreMissingRowsById('conversations', $conversationIds, $dryRun),
            'messages' => $this->restoreMessageParents($removedId, $dryRun),
        ];

        foreach (self::TABLE_REGISTRY as $definition) {
            if (! Schema::connection('backup_db')->hasTable($definition['table'])) {
                continue;
            }

            $counts[$definition['table']] = $this->restoreTable(
                $definition['table'],
                $definition['columns'],
                $removedId,
                $dryRun,
                $definition['compositeKey'] ?? null,
            );
        }

        $counts['notifications_params'] = $this->restoreNotificationParams($removedId, $dryRun);

        return array_filter($counts, fn (int $count) => $count > 0);
    }

    /**
     * @param  list<string>  $columns
     * @param  list<string>|null  $compositeKey
     */
    private function restoreTable(string $table, array $columns, int $removedId, bool $dryRun, ?array $compositeKey = null): int
    {
        $backupRows = $this->backupRowsForUser($table, $columns, $removedId);
        $restored = 0;

        foreach ($backupRows as $row) {
            $rowArray = $this->rowToArray($row);

            if (! $this->hasKeyColumns($rowArray, $compositeKey)) {
                continue;
            }

            if ($this->rowExistsOnProduction($table, $rowArray, $compositeKey)) {
                continue;
            }

            if (! $dryRun) {
                DB::table($table)->insert($rowArray);
            }

            $restored++;
        }

        return $restored;
    }

    /**
     * @param  array<string, mixed>  $rowArray
     * @param  list<string>|null  $compositeKey
     */
    private function rowExistsOnProduction(string $table, array $rowArray, ?array $compositeKey): bool
    {
        if ($compositeKey !== null) {
            $query = DB::table($table);
            foreach ($compositeKey as $column) {
                $query->where($column, $rowArray[$column]);
            }

            return $query->exists();
        }

        if (! array_key_exists('id', $rowArray)) {
            return true;
        }

        return DB::table($table)->where('id', $rowArray['id'])->exists();
    }

    /**
     * @param  array<string, mixed>  $rowArray
     * @param  list<string>|null  $keyColumns
     */
    private function hasKeyColumns(array $rowArray, ?array $keyColumns): bool
    {
        $columns = $keyColumns ?? ['id'];

        foreach ($columns as $column) {
            if (! array_key_exists($column, $rowArray)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    private function rowToArray(object|array $row): array
    {
        if (is_array($row)) {
            return $row;
        }

        return json_decode(json_encode($row), true, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * @return list<int>
     */
    private function conversationIdsForRemovedUser(int $removedId): array
    {
        $conversationIds = [];

        if (Schema::connection('backup_db')->hasTable('conversations_users')) {
            $conversationIds = array_merge(
                $conversationIds,
                DB::connection('backup_db')->table('conversations_users')
                    ->where('user_id', $removedId)
                    ->pluck('conversation_id')
                    ->all()
            );
        }

        if (Schema::connection('backup_db')->hasTable('messages')) {
            $conversationIds = array_merge(
                $conversationIds,
                DB::connection('backup_db')->table('messages')
                    ->where('user_id', $removedId)
                    ->pluck('conversation_id')
                    ->all()
            );
        }

        return array_values(array_unique(array_map('intval', $conversationIds)));
    }

    /**
     * @param  list<int>  $conversationIds
     */
    private function restoreTripParentsForConversations(array $conversationIds, bool $dryRun): int
    {
        if ($conversationIds === [] || ! Schema::connection('backup_db')->hasTable('conversations')) {
            return 0;
        }

        $tripIds = DB::connection('backup_db')->table('conversations')
            ->whereIn('id', $conversationIds)
            ->whereNotNull('trip_id')
            ->pluck('trip_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        return $this->restoreMissingTripsById($tripIds, $dryRun);
    }

    /**
     * @param  list<int>  $ids
     */
    private function restoreMissingTripsById(array $ids, bool $dryRun): int
    {
        $closureIds = $this->expandTripDependencyClosure($ids);
        $missingIds = array_values(array_filter(
            $closureIds,
            fn (int $tripId): bool => ! DB::table('trips')->where('id', $tripId)->exists()
        ));

        if ($missingIds === []) {
            return 0;
        }

        if ($dryRun) {
            return count($missingIds);
        }

        $backupRows = [];
        foreach ($missingIds as $tripId) {
            $row = DB::connection('backup_db')->table('trips')->where('id', $tripId)->first();

            if ($row === null) {
                continue;
            }

            $backupRows[$tripId] = $this->rowToArray($row);
        }

        $restored = 0;

        foreach ($backupRows as $tripId => $rowArray) {
            foreach (['parent_trip_id', 'return_trip_id'] as $column) {
                if (empty($rowArray[$column])) {
                    continue;
                }

                $relatedTripId = (int) $rowArray[$column];

                if (array_key_exists($relatedTripId, $backupRows)
                    && ! DB::table('trips')->where('id', $relatedTripId)->exists()) {
                    $rowArray[$column] = null;
                }
            }

            DB::table('trips')->insert($rowArray);
            $restored++;
        }

        foreach ($backupRows as $tripId => $rowArray) {
            $this->wireTripSelfReferences($tripId, $rowArray);
        }

        return $restored;
    }

    /**
     * @param  list<int>  $seedIds
     * @return list<int>
     */
    private function expandTripDependencyClosure(array $seedIds): array
    {
        $pending = array_values(array_unique(array_map('intval', $seedIds)));
        $closure = [];

        while ($pending !== []) {
            $tripId = array_shift($pending);

            if (isset($closure[$tripId])) {
                continue;
            }

            $row = DB::connection('backup_db')->table('trips')->where('id', $tripId)->first();

            if ($row === null) {
                continue;
            }

            $closure[$tripId] = true;

            foreach (['parent_trip_id', 'return_trip_id'] as $column) {
                $relatedTripId = $row->{$column} ?? null;

                if ($relatedTripId !== null && ! isset($closure[(int) $relatedTripId])) {
                    $pending[] = (int) $relatedTripId;
                }
            }
        }

        return array_map('intval', array_keys($closure));
    }

    /**
     * @param  array<string, mixed>  $backupRow
     */
    private function wireTripSelfReferences(int $tripId, array $backupRow): void
    {
        $updates = [];

        foreach (['parent_trip_id', 'return_trip_id'] as $column) {
            if (empty($backupRow[$column])) {
                continue;
            }

            $relatedTripId = (int) $backupRow[$column];

            if (DB::table('trips')->where('id', $relatedTripId)->exists()) {
                $updates[$column] = $relatedTripId;
            }
        }

        if ($updates !== []) {
            DB::table('trips')->where('id', $tripId)->update($updates);
        }
    }

    private function restoreMessageParents(int $removedId, bool $dryRun): int
    {
        if (! Schema::connection('backup_db')->hasTable('messages')) {
            return 0;
        }

        $messageIds = DB::connection('backup_db')->table('messages')
            ->where('user_id', $removedId)
            ->pluck('id')
            ->all();

        if (Schema::connection('backup_db')->hasTable('user_message_read')) {
            $messageIds = array_merge(
                $messageIds,
                DB::connection('backup_db')->table('user_message_read')
                    ->where('user_id', $removedId)
                    ->pluck('message_id')
                    ->all()
            );
        }

        $messageIds = array_values(array_unique(array_map('intval', $messageIds)));

        if ($messageIds === []) {
            return 0;
        }

        $conversationIds = DB::connection('backup_db')->table('messages')
            ->whereIn('id', $messageIds)
            ->pluck('conversation_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        $this->restoreTripParentsForConversations($conversationIds, $dryRun);
        $this->restoreMissingRowsById('conversations', $conversationIds, $dryRun);

        return $this->restoreMissingRowsById('messages', $messageIds, $dryRun);
    }

    /**
     * @param  list<int>  $ids
     */
    private function restoreMissingRowsById(string $table, array $ids, bool $dryRun): int
    {
        if ($ids === []) {
            return 0;
        }

        $existingIds = DB::table($table)
            ->whereIn('id', $ids)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $missingIds = array_values(array_diff($ids, $existingIds));
        $restored = 0;

        foreach ($missingIds as $id) {
            $row = DB::connection('backup_db')->table($table)->where('id', $id)->first();

            if ($row === null) {
                continue;
            }

            if (! $dryRun) {
                DB::table($table)->insert($this->rowToArray($row));
            }

            $restored++;
        }

        return $restored;
    }

    private function restoreNotificationParams(int $removedId, bool $dryRun): int
    {
        if (! Schema::connection('backup_db')->hasTable('notifications_params')) {
            return 0;
        }

        $notificationIds = DB::connection('backup_db')
            ->table('notifications')
            ->where('user_id', $removedId)
            ->pluck('id')
            ->all();

        if ($notificationIds === []) {
            return 0;
        }

        $rows = DB::connection('backup_db')
            ->table('notifications_params')
            ->whereIn('notification_id', $notificationIds)
            ->get();

        $restored = 0;
        foreach ($rows as $row) {
            $rowArray = $this->rowToArray($row);

            if (! array_key_exists('id', $rowArray)) {
                continue;
            }

            $exists = DB::table('notifications_params')
                ->where('id', $rowArray['id'])
                ->exists();

            if ($exists) {
                continue;
            }

            if (! $dryRun) {
                DB::table('notifications_params')->insert($rowArray);
            }

            $restored++;
        }

        return $restored;
    }

    /**
     * @param  list<string>  $columns
     * @return list<object>
     */
    private function backupRowsForUser(string $table, array $columns, int $removedId): array
    {
        $query = DB::connection('backup_db')->table($table);

        $query->where(function ($builder) use ($columns, $removedId): void {
            foreach ($columns as $index => $column) {
                if ($index === 0) {
                    $builder->where($column, $removedId);
                } else {
                    $builder->orWhere($column, $removedId);
                }
            }
        });

        return $query->get()->all();
    }
}
