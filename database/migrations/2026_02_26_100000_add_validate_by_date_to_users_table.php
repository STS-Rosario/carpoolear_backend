<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * validate_by_date: optional deadline for existing users to complete identity validation; null for new users (created after identity_validation_new_users_date).
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->date('validate_by_date')->nullable()->after('identity_validation_reject_reason');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('validate_by_date');
        });
    }
};
