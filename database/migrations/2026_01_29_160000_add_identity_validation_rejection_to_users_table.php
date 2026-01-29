<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('identity_validation_rejected_at')->nullable()->after('identity_validation_type');
            $table->string('identity_validation_reject_reason', 100)->nullable()->after('identity_validation_rejected_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['identity_validation_rejected_at', 'identity_validation_reject_reason']);
        });
    }
};
