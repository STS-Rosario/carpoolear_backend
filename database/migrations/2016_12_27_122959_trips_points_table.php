<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class TripsPointsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('trips_points', function (Blueprint $table) {
			$table->engine = 'InnoDB';
            $table->increments('id');
            $table->integer('trip_id')->unsigned();
            $table->string('address', 255);
            $table->string('json_address');

            $table->double('lat');
            $table->double('lng');

            $table->double('sin_lat');
            $table->double('cos_lat');
            $table->double('sin_lng');
            $table->double('cos_lng');

            $table->timestamps();

            $table->foreign('trip_id')->references('id')->on('trips')->onUpdate('cascade')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('trips_points');
    }
}
