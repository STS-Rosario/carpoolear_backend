<?php

namespace Tests\Unit\Config;

use Illuminate\Support\Facades\DB;
use PDO;
use Tests\TestCase;

class BackupDatabaseConnectionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->ensureBackupDatabaseExists();
    }

    private function ensureBackupDatabaseExists(): void
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

    public function test_backup_db_connection_is_configured(): void
    {
        $config = config('database.connections.backup_db');

        $this->assertIsArray($config);
        $this->assertSame('mysql', $config['driver']);
        $this->assertNotEmpty($config['database']);
    }

    public function test_backup_db_connection_can_connect(): void
    {
        $pdo = DB::connection('backup_db')->getPdo();

        $this->assertNotNull($pdo);
    }
}
