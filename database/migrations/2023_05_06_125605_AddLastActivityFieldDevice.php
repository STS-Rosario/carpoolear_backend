<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddLastActivityFieldDevice extends Migration
{
    public function up()
    {
        Schema::table('users_devices', function (Blueprint $table) {
            $table->date('last_activity')->nullable();
          
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
            $table->dropColumn('last_activity');
        });
    }
}
