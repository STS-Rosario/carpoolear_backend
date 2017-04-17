<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddNotificationsFieldDevices extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('users_devices', function (Blueprint $table) {
            $table->boolean('notifications');
            $table->string('language');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('users_devices', function (Blueprint $table) {
            $table->dropColumn('language');
            $table->dropColumn('notifications');
        });
    }
}
