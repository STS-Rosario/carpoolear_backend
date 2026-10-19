<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('club_carpoolear_welcome_email_sent_at')->nullable()->after('club_carpoolear_welcome_shown');
        });

        DB::table('users')
            ->whereNotNull('club_carpoolear_joined_at')
            ->update(['club_carpoolear_welcome_email_sent_at' => now()]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('club_carpoolear_welcome_email_sent_at');
        });
    }
};
