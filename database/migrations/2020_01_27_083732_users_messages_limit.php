<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class UsersMessagesLimit extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->integer('unaswered_messages_limit')->nullable();
            $table->integer('conversation_opened_count')->nullable();
            $table->double('answer_delay_sum')->nullable();
            $table->boolean('send_full_trip_message')->default(1)->nullable();
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
            $table->dropColumn('unaswered_messages_limit');
            $table->dropColumn('conversation_opened_count');
            $table->dropColumn('answer_delay_sum');
            $table->dropColumn('send_full_trip_message');
        });
    }
}