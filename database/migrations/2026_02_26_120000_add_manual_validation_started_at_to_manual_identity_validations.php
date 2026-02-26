<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Tracks when an admin first interacted with the request (approve/reject/pending).
     */
    public function up(): void
    {
        Schema::table('manual_identity_validations', function (Blueprint $table) {
            $table->timestamp('manual_validation_started_at')->nullable()->after('review_note');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('manual_identity_validations', function (Blueprint $table) {
            $table->dropColumn('manual_validation_started_at');
        });
    }
};
