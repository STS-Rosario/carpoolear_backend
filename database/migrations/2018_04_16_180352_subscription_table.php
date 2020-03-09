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
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->increments('id');
            $table->integer('user_id')->unsigned();
            $table->datetime('trip_date')->nullable();

            $table->string('from_address', 255)->nullable();
            $table->string('from_json_address')->nullable();
            $table->double('from_lat')->nullable();
            $table->double('from_lng')->nullable();
            $table->double('from_radio')->nullable();
            $table->double('from_sin_lat')->nullable();
            $table->double('from_cos_lat')->nullable();
            $table->double('from_sin_lng')->nullable();
            $table->double('from_cos_lng')->nullable();

            $table->string('to_address', 255)->nullable();
            $table->string('to_json_address')->nullable();
            $table->double('to_lat')->nullable();
            $table->double('to_lng')->nullable();
            $table->double('to_radio')->nullable();
            $table->double('to_sin_lat')->nullable();
            $table->double('to_cos_lat')->nullable();
            $table->double('to_sin_lng')->nullable();
            $table->double('to_cos_lng')->nullable();

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
        Schema::drop('subscriptions');
    }
}
