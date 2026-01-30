<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddUserPrivateNote extends Migration
{
	public function up()
	{
		Schema::table('trips', function (Blueprint $table) {
			// Add weekly_schedule column as an integer to store bitmask
			// This allows any combination of days to be stored in a single integer
			if (!Schema::hasColumn('trips', 'weekly_schedule')) {
				$table->unsignedTinyInteger('weekly_schedule')->after('trip_date')->index();
			}

			// Add weekly_schedule_time column to store the time for weekly schedule trips
			if (!Schema::hasColumn('trips', 'weekly_schedule_time')) {
				$table->time('weekly_schedule_time')->nullable()->after('weekly_schedule');
			}

			// Make trip_date nullable to support weekly schedule trips
			// Trips with weekly_schedule don't need a specific date
			$table->datetime('trip_date')->nullable()->change();
		});
	}

	public function down()
	{
		Schema::table('trips', function (Blueprint $table) {
			$table->dropColumn('weekly_schedule');
			$table->dropColumn('weekly_schedule_time');

			// Revert trip_date to not nullable
			$table->datetime('trip_date')->nullable(false)->change();
		});
	}
}