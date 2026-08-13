<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use STS\Support\TripExcessContributionStatus;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trips', function (Blueprint $table) {
            $table->string('exceso_contribucion_status', 32)->nullable();
        });

        DB::table('trips')
            ->where('has_potential_excess_contribution', true)
            ->whereNull('exceso_contribucion_status')
            ->update(['exceso_contribucion_status' => TripExcessContributionStatus::PENDIENTE]);
    }

    public function down(): void
    {
        Schema::table('trips', function (Blueprint $table) {
            $table->dropColumn('exceso_contribucion_status');
        });
    }
};
