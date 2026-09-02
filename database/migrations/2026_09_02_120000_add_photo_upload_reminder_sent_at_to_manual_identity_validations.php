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
        Schema::table('manual_identity_validations', function (Blueprint $table) {
            $table->timestamp('photos_upload_reminder_week1_sent_at')->nullable()->after('images_purged_at');
            $table->timestamp('photos_upload_reminder_week2_sent_at')->nullable()->after('photos_upload_reminder_week1_sent_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('manual_identity_validations', function (Blueprint $table) {
            $table->dropColumn([
                'photos_upload_reminder_week1_sent_at',
                'photos_upload_reminder_week2_sent_at',
            ]);
        });
    }
};
