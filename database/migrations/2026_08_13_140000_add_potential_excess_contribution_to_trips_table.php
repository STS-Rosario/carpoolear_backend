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
            $table->boolean('has_potential_excess_contribution')->default(false);
            $table->unsignedInteger('description_potential_seat_price_cents')->nullable();
        });

        Trip::query()
            ->where('seat_price_cents', '>', 0)
            ->whereNotNull('description')
            ->where('description', '!=', '')
            ->where(function ($query) {
                $query->where('description', 'like', '%$%')
                    ->orWhere('description', 'like', '%luca%');
            })
            ->orderBy('id')
            ->chunkById(200, function ($trips) {
                foreach ($trips as $trip) {
                    TripDescriptionContributionHelper::syncPotentialExcessContributionAttributes($trip);

                    if ($trip->isDirty([
                        'has_potential_excess_contribution',
                        'description_potential_seat_price_cents',
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
                'has_potential_excess_contribution',
                'description_potential_seat_price_cents',
            ]);
        });
    }
};
