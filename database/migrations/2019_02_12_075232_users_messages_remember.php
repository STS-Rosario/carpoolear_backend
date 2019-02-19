<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class UsersMessagesRemember extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('do_not_alert_request_seat');
            $table->boolean('do_not_alert_accept_passenger');
            $table->boolean('do_not_alert_pending_rates');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('do_not_alert_request_seat')->default(false);
            $table->dropColumn('do_not_alert_accept_passenger')->default(false);
            $table->dropColumn('do_not_alert_pending_rates')->default(false);
        });
    }
}
