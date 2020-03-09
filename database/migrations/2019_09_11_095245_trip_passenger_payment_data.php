<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class TripPassengerPaymentData extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('trip_passengers', function (Blueprint $table) {
            $table->double('price')->nullable();
            $table->string('payment_status', 127)->nullable();
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
            $table->dropColumn('price');
            $table->dropColumn('payment_status');
        });
    }
}
