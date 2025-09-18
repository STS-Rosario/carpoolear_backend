<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('trip_searches', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->increments('id');
            $table->integer('user_id')->unsigned()->nullable();
            $table->integer('origin_id')->unsigned()->nullable();
            $table->integer('destination_id')->unsigned()->nullable();
            $table->timestamp('search_date')->nullable();
            $table->timestamps();
            $table->integer('amount_trips')->default(0);
            $table->integer('amount_trips_carpooleados')->default(0);
            $table->tinyInteger('client_platform')->default(0); // 0: web, 1: ios, 2: android
            $table->tinyInteger('is_passenger')->default(0); // 0: driver trip, 1: passenger trip
            $table->json('results_json')->nullable();

            // Foreign key constraints
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
            $table->foreign('origin_id')->references('id')->on('nodes_geo')->onDelete('set null');
            $table->foreign('destination_id')->references('id')->on('nodes_geo')->onDelete('set null');

            // Indexes for better performance
            $table->index(['user_id', 'search_date']);
            $table->index(['origin_id', 'destination_id']);
            $table->index(['origin_id']); // For searches with only origin
            $table->index(['destination_id']); // For searches with only destination
            $table->index('search_date');
            $table->index('is_passenger'); // For filtering by trip type
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trip_searches');
    }
};
