<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class TripPassengersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('trip_passengers', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->increments('id');
            $table->integer('user_id')->unsigned();
            $table->integer('trip_id')->unsigned();
            $table->integer('passenger_type');
            $table->integer('request_state');
            $table->timestamps();
            $table->foreign('user_id')->references('id')->on('users')
                                         ->onDelete('cascade')
                                         ->onUpdate('cascade');
            $table->foreign('trip_id')->references('id')->on('trips')
                                         ->onDelete('cascade')
                                         ->onUpdate('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('trip_passengers');
    }
}
