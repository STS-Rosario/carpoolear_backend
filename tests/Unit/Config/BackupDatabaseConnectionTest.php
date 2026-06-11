<?php

namespace Tests\Unit\Config;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BackupDatabaseConnectionTest extends TestCase
{
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
