<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddCanceledState extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('trip_passengers', function (Blueprint $table) {
			$table->engine = 'InnoDB';
            $table->integer('canceled_state')->after('request_state')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('trip_passengers', function (Blueprint $table) {
            $table->dropColumn('canceled_state');
        });
    }
}
