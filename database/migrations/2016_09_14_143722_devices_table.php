<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class DevicesTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('users_devices', function(Blueprint $table)
		{
			$table->engine = 'InnoDB';
            $table->increments('id');
            $table->bigInteger("user_id")->unsigned();
            $table->string('session_id', 500);
            $table->string('device_id', 500);
            $table->string('device_type', 12);
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
		Schema::drop('users_devices');
	}

}
