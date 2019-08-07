<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CalificacionesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('rating', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->increments('id');
            $table->integer('trip_id')->unsigned();
            $table->integer('user_id_from')->unsigned();
            $table->integer('user_id_to')->unsigned();
            $table->integer('user_to_type');
            $table->integer('user_to_state');
            $table->integer('rating')->nullable();
            $table->text('comment');
            $table->string('reply_comment');
            $table->datetime('reply_comment_created_at')->nullable();
            $table->boolean('voted');
            $table->datetime('rate_at')->nullable();
            $table->string('voted_hash');
            $table->timestamps();
            $table->foreign('user_id_from')->references('id')->on('users')
                                         ->onDelete('cascade')
                                         ->onUpdate('cascade');
            $table->foreign('user_id_to')->references('id')->on('users')
                                         ->onDelete('cascade')
                                         ->onUpdate('cascade');
            $table->foreign('trip_id')->references('id')->on('trips')
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
        Schema::drop('rating');
    }
}
