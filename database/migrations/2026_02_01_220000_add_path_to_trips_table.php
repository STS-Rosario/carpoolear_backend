<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trips', function (Blueprint $table) {
            $table->string('path', 255)->after('weekly_schedule_time');
        });

        // Prefill path for active trips (scheduled after now OR weekly schedule)
        $activeTrips = DB::table('trips')
            ->where(function ($query) {
                $query->where('trip_date', '>=', Carbon::now())
                    ->orWhere('weekly_schedule', '>', 0);
            })
            ->get();

        foreach ($activeTrips as $trip) {
            $points = DB::table('trips_points')
                ->where('trip_id', $trip->id)
                ->orderBy('id')
                ->get();

            $nodeIds = collect($points)
                ->map(fn($point) => ((object)$point->json_address)->id ?? null)
                ->filter(fn($id) => $id > 0)
                ->values();

            if ($nodeIds->isNotEmpty()) {
                $path = '.' . $nodeIds->implode('.') . '.';
                DB::table('trips')
                    ->where('id', $trip->id)
                    ->update(['path' => $path]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('trips', function (Blueprint $table) {
            $table->dropColumn('path');
        });
    }
};
