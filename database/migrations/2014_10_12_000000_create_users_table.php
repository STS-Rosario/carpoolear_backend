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
			$table->string('username',255)->nullable();
			$table->string('name');
			$table->string('email')->unique();
			$table->string('password', 60);

			$table->integer("terms_and_conditions");
			$table->date("birthday")->nullable();
			$table->string('gender',255)->nullable();

			$table->string("nro_doc",15);
			$table->string("patente",15);
			$table->string("descripcion",500);
			$table->string("mobile_phone",50);
			$table->string("l_perfil",255);

			

			$able->boolean("banned");

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
