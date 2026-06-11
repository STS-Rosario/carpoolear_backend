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
        ['table' => 'conversations_users', 'columns' => ['user_id']],
        ['table' => 'user_message_read', 'columns' => ['user_id']],
        ['table' => 'messages', 'columns' => ['user_id']],
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
        $counts = [];

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
     */
    /**
     * @param  list<string>  $columns
     * @param  list<string>|null  $compositeKey
     */
    private function restoreTable(string $table, array $columns, int $removedId, bool $dryRun, ?array $compositeKey = null): int
    {
        $backupRows = $this->backupRowsForUser($table, $columns, $removedId);
        $restored = 0;

        foreach ($backupRows as $row) {
            $rowArray = (array) $row;

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

        return DB::table($table)->where('id', $rowArray['id'])->exists();
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
            $rowArray = (array) $row;
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
