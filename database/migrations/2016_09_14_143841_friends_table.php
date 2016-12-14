<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class FriendsTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('friends', function(Blueprint $table)
		{
			$table->bigInteger('uid1')->unsigned();	
            $table->bigInteger('uid2')->unsigned();
            $table->foreign('uid1')  ->references('id')->on('users')->onUpdate('cascade')->onDelete('cascade'); 
            $table->foreign('uid2')->references('id')->on('users')->onUpdate('cascade')->onDelete('cascade'); 	
			$table->integer("type");
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
