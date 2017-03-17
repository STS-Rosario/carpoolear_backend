<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class FriendsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('friends', function (Blueprint $table) {
            $table->integer('uid1')->unsigned();
            $table->integer('uid2')->unsigned();
            $table->string('origin');
            $table->integer('state');

            $table->foreign('uid1')->references('id')->on('users')->onUpdate('cascade')->onDelete('cascade');
            $table->foreign('uid2')->references('id')->on('users')->onUpdate('cascade')->onDelete('cascade');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('friends');
    }
}
