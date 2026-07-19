<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('support_tickets', function (Blueprint $table) {
            $table->unsignedInteger('assigned_to_user_id')->nullable()->after('closed_at');
            $table->timestamp('assigned_at')->nullable()->after('assigned_to_user_id');
            $table->foreign('assigned_to_user_id')->references('id')->on('users')->nullOnDelete();
            $table->index(['assigned_to_user_id', 'assigned_at']);
        });
    }

    public function down(): void
    {
        Schema::table('support_tickets', function (Blueprint $table) {
            $table->dropForeign(['assigned_to_user_id']);
            $table->dropIndex(['assigned_to_user_id', 'assigned_at']);
            $table->dropColumn(['assigned_to_user_id', 'assigned_at']);
        });
    }
};
