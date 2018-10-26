<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddCarsFieldTrips extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('trips', function (Blueprint $table) {
			$table->engine = 'InnoDB';
            $table->integer('car_id')->unsigned()->nullable();
            $table->foreign('car_id')->references('id')->on('cars')->onUpdate('cascade')->onDelete('set null');
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
            $table->dropForeign('trips_car_id_foreign');
            $table->dropColumn('car_id');
        });
    }
}
