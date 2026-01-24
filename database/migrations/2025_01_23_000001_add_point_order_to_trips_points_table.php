<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddPointOrderToTripsPointsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasColumn('trips_points', 'point_order')) {
            Schema::table('trips_points', function (Blueprint $table) {
                $table->integer('point_order')->after('trip_id')->default(0);
            });

            // Backfill existing data with point_order based on id (assuming points were created sequentially)
            DB::statement('
                UPDATE trips_points t1
                SET point_order = (
                    SELECT COUNT(*)
                    FROM trips_points t2
                    WHERE t2.trip_id = t1.trip_id AND t2.id <= t1.id
                ) - 1
            ');
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('trips_points', function (Blueprint $table) {
            $table->dropColumn('point_order');
        });
    }
}
