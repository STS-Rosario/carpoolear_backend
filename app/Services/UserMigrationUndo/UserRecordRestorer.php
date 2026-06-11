<?php

namespace STS\Services\UserMigrationUndo;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use STS\Models\User;
use STS\Services\UserMigrationFieldMerger;

class UserRecordRestorer
{
    /**
     * @return array{restored: int, updated: int}
     */
    public function restore(int $keptId, int $removedId, bool $dryRun): array
    {
        $restored = $this->restoreRemovedUser($removedId, $dryRun);
        $updated = $this->restoreKeptUser($keptId, $dryRun);

        return [
            'restored' => $restored,
            'updated' => $updated,
        ];
    }

    private function restoreRemovedUser(int $removedId, bool $dryRun): int
    {
        $backupUser = DB::connection('backup_db')
            ->table('users')
            ->where('id', $removedId)
            ->first();

        if ($backupUser === null) {
            return 0;
        }

        $backupRow = (array) $backupUser;
        $prodExists = User::query()->whereKey($removedId)->exists();

        if (! $dryRun) {
            $this->assertEmailAvailable($backupRow['email'] ?? null, $removedId);

            if ($prodExists) {
                unset($backupRow['id']);
                DB::table('users')->where('id', $removedId)->update($backupRow);
            } else {
                DB::table('users')->insert($backupRow);
            }
        }

        return 1;
    }

    private function restoreKeptUser(int $keptId, bool $dryRun): int
    {
        $backupUser = DB::connection('backup_db')
            ->table('users')
            ->where('id', $keptId)
            ->first();

        if ($backupUser === null) {
            return 0;
        }

        $fields = array_merge(
            UserMigrationFieldMerger::MERGEABLE_FIELDS,
            ['name', 'active', 'username', 'birthday', 'gender', 'description', 'image', 'mobile_phone']
        );
        $fields = array_unique($fields);

        $backupArray = (array) $backupUser;
        $updates = [];
        foreach ($fields as $field) {
            if (array_key_exists($field, $backupArray)) {
                $updates[$field] = $backupArray[$field];
            }
        }

        if ($updates === []) {
            return 0;
        }

        if (! $dryRun) {
            $this->assertEmailAvailable($updates['email'] ?? null, $keptId);
            DB::table('users')->where('id', $keptId)->update($updates);
        }

        return 1;
    }

    private function assertEmailAvailable(?string $email, int $exceptUserId): void
    {
        if ($email === null || $email === '') {
            return;
        }

        $conflict = User::query()
            ->where('email', $email)
            ->where('id', '!=', $exceptUserId)
            ->exists();

        if ($conflict) {
            throw new InvalidArgumentException("Email [{$email}] is already used by another user.");
        }
    }
}
