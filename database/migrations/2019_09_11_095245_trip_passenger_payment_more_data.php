<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class TripPassengerPaymentMoreData extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('trip_passengers', function (Blueprint $table) {
            $table->string('payment_info', 2047)->nullable();
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
            $table->dropColumn('payment_info');
        });
    }
}
