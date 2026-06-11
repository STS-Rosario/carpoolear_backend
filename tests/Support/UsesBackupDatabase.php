<?php

namespace Tests\Support;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use PDO;

trait UsesBackupDatabase
{
    protected static bool $backupDatabaseMigrated = false;

    /**
     * @return list<string>
     */
    protected function connectionsToTransact(): array
    {
        return ['mysql', 'backup_db'];
    }

    protected function setUpBackupDatabase(): void
    {
        $this->ensureBackupDatabaseExists();

        if (! static::$backupDatabaseMigrated) {
            Artisan::call('migrate:fresh', [
                '--database' => 'backup_db',
                '--drop-views' => true,
                '--force' => true,
            ]);
            static::$backupDatabaseMigrated = true;
        }
    }

    protected function ensureBackupDatabaseExists(): void
    {
        $database = config('database.connections.backup_db.database');
        $mysql = config('database.connections.mysql');

        $dsn = sprintf(
            'mysql:host=%s;port=%s',
            $mysql['host'],
            $mysql['port']
        );

        $pdo = new PDO($dsn, $mysql['username'], $mysql['password']);
        $pdo->exec(sprintf(
            'CREATE DATABASE IF NOT EXISTS `%s`',
            str_replace('`', '``', $database)
        ));
    }

    /**
     * @param  list<string>  $tables
     */
    protected function copyTablesToBackup(array $tables): void
    {
        foreach ($tables as $table) {
            DB::connection('backup_db')->table($table)->delete();

            $rows = DB::table($table)->get();
            foreach ($rows as $row) {
                DB::connection('backup_db')->table($table)->insert((array) $row);
            }
        }
    }

    /**
     * @param  list<string>  $tables
     */
    protected function copyTablesFromBackup(array $tables): void
    {
        foreach ($tables as $table) {
            DB::table($table)->delete();

            $rows = DB::connection('backup_db')->table($table)->get();
            foreach ($rows as $row) {
                DB::table($table)->insert((array) $row);
            }
        }
    }
}
