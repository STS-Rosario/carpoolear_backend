<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class TripsTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('trips', function(Blueprint $table)
		{
			$table->increments('id');
			$table->integer("user_id")->unsigned();

			$table->string("from_town", 500);
			$table->string("to_town", 500);
			$table->datetime("trip_date");
			$table->string('description', 1500);
			$table->integer('total_seats');
			$table->integer("friendship_type_id");
			$table->integer("is_active");
			$table->double("distance");
			$table->string('estimated_time', 500);
			$table->integer("co2");
			$table->integer("es_recurrente");
			$table->integer("es-pasajero");
			$table->integer("esta_carpooleado");
			$table->boolean("mail_send");
			$table->string('tripscol', 45);

			$table->timestamps();
			$table->softDeletes();

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
		Schema::drop('trips');
	}

}
