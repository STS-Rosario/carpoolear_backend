<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversations_users', function (Blueprint $table) {
            $table->boolean('notifications_enabled')->default(true)->after('read');
        });
    }

    public function down(): void
    {
        Schema::table('conversations_users', function (Blueprint $table) {
            $table->dropColumn('notifications_enabled');
        });
    }
};
