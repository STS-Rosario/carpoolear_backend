<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Legacy production tables were created with MySQL "utf8" (utf8mb3), which
     * cannot store 4-byte characters such as emojis. Laravel connects as
     * utf8mb4, which triggers MySQL error 3988 on writes like "Belgrano🩵".
     */
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        $database = DB::getDatabaseName();

        $tables = DB::table('information_schema.columns as columns')
            ->join('information_schema.tables as tables', function ($join) {
                $join->on('columns.TABLE_SCHEMA', '=', 'tables.TABLE_SCHEMA')
                    ->on('columns.TABLE_NAME', '=', 'tables.TABLE_NAME');
            })
            ->select('columns.TABLE_NAME')
            ->where('columns.TABLE_SCHEMA', $database)
            ->where('tables.TABLE_TYPE', 'BASE TABLE')
            ->whereNotNull('columns.CHARACTER_SET_NAME')
            ->where('columns.CHARACTER_SET_NAME', '!=', 'utf8mb4')
            ->distinct()
            ->orderBy('columns.TABLE_NAME')
            ->pluck('TABLE_NAME');

        if ($tables->isEmpty()) {
            return;
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        foreach ($tables as $table) {
            DB::statement(
                "ALTER TABLE `{$table}` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
            );
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    public function down(): void
    {
        // Irreversible once 4-byte UTF-8 characters have been stored.
    }
};
