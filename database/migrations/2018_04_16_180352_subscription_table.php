<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class SubscriptionTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('subscription', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('user_id')->unsigned();
            $table->datetime('trip_date');

            $table->string('from_address', 255);
            $table->string('from_json_address');
            $table->double('from_lat');
            $table->double('from_lng');

            $table->string('to_address', 255);
            $table->string('to_json_address');
            $table->double('to_lat');
            $table->double('to_lng');

            $table->boolean('state');

            $table->timestamps();
            $table->foreign('user_id')->references('id')->on('users')
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
        Schema::drop('subscription');
    }
}
