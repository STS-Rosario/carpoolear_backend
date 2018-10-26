<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTableNotificationsParams extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('notifications_params', function (Blueprint $table) {
			$table->engine = 'InnoDB';
            $table->increments('id');
            $table->integer('notification_id')->unsigned();
            $table->string('key', 50);
            $table->morphs('value');
            $table->text('value_text')->nullable();
            $table->timestamps();
            $table->foreign('notification_id')->references('id')->on('notifications')
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
        Schema::drop('notifications_params');
    }
}
