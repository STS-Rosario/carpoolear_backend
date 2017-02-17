<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateUsersTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('users', function(Blueprint $table)
		{
			$table->increments('id');
			//$table->bigInteger('facebook_uid')->unique();
			//$table->string('username',255)->nullable();
			$table->string('name');
			$table->string('email')->unique();
			$table->string('password', 60)->nullable();

			$table->boolean("terms_and_conditions");
			$table->date("birthday")->nullable();
			$table->string('gender',255)->nullable();

			$table->string("nro_doc",15);
			$table->string("patente",15);
			$table->string("description",500);
			$table->string("mobile_phone",50);
			$table->string("image",255);			

			$table->boolean("banned");
			$table->boolean("is_admin");

			$table->rememberToken();
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
		Schema::drop('users');
	}

}
