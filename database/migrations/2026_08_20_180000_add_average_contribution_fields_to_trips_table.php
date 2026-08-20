<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use STS\Helpers\TripDescriptionContributionHelper;
use STS\Models\Trip;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trips', function (Blueprint $table) {
            $table->unsignedInteger('average_contribution_cents')->nullable();
            $table->unsignedSmallInteger('excess_contribution_percentage')->nullable();
        });

        Trip::query()
            ->where('has_potential_excess_contribution', true)
            ->orderBy('id')
            ->chunkById(200, function ($trips) {
                foreach ($trips as $trip) {
                    TripDescriptionContributionHelper::syncPotentialExcessContributionAttributes($trip);

                    if ($trip->isDirty([
                        'average_contribution_cents',
                        'excess_contribution_percentage',
                    ])) {
                        $trip->saveQuietly();
                    }
                }
            });
    }

    public function down(): void
    {
        Schema::table('trips', function (Blueprint $table) {
            $table->dropColumn([
                'average_contribution_cents',
                'excess_contribution_percentage',
            ]);
        });
    }
};
