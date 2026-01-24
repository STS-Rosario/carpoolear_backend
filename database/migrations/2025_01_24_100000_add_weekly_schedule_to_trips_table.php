<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('trips', function (Blueprint $table) {
            // Add weekly_schedule column as an integer to store bitmask
            // This allows any combination of days to be stored in a single integer
            if (!Schema::hasColumn('trips', 'weekly_schedule')) {
                $table->unsignedInteger('weekly_schedule')->nullable()->after('trip_date');
            }

            // Make trip_date nullable to support weekly schedule trips
            // Trips with weekly_schedule don't need a specific date
            $table->datetime('trip_date')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('trips', function (Blueprint $table) {
            $table->dropColumn('weekly_schedule');
            // Revert trip_date to not nullable
            $table->datetime('trip_date')->nullable(false)->change();
        });
    }
};
