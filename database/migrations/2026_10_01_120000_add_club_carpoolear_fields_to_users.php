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
            $table->timestamp('club_carpoolear_joined_at')->nullable()->after('monthly_donate');
            $table->boolean('show_club_carpoolear_membership')->default(true)->after('club_carpoolear_joined_at');
        });

        $exists = DB::table('badges')->where('slug', 'club-carpoolear')->exists();
        if (! $exists) {
            DB::table('badges')->insert([
                'title' => 'Club Carpoolear',
                'slug' => 'club-carpoolear',
                'description' => 'Miembro activo del Club Carpoolear',
                'image_path' => 'badges/club-carpoolear.png',
                'rules' => json_encode(['type' => 'club_carpoolear']),
                'visible' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['club_carpoolear_joined_at', 'show_club_carpoolear_membership']);
        });

        DB::table('badges')->where('slug', 'club-carpoolear')->delete();
    }
};
