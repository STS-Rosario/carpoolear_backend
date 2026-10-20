<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

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
