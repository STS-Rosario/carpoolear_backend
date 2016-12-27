<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CalificacionesTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('calificaciones', function(Blueprint $table)
		{
			$table->increments('id');
			$table->integer("viajes_id")->unsigned(); 
			$table->integer("activo_id")->unsigned();
			$table->integer("pasivo_id")->unsigned();
			$table->integer("puntuacion");
			$table->string("descripcion");
			$table->integer("viajo");
			$table->integer("tipo_pasajero");
			$table->timestamps();
			$table->foreign('activo_id')->references('id')->on('users')
			                             ->onDelete('cascade')
										 ->onUpdate('cascade');
			$table->foreign('pasivo_id')->references('id')->on('users')
			                             ->onDelete('cascade')
										 ->onUpdate('cascade');							 
			$table->foreign('viajes_id')->references('id')->on('trips')
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
		Schema::drop('calificaciones');
	}

}
