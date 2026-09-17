<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('admin_action_logs', function (Blueprint $table) {
            $table->unsignedInteger('target_user_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('admin_action_logs', function (Blueprint $table) {
            $table->unsignedInteger('target_user_id')->nullable(false)->change();
        });
    }
};
