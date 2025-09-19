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
        Schema::create('route_cache', function (Blueprint $table) {
            $table->id();
            $table->json('points');
            $table->string('hashed_points', 64)->index();
            $table->json('route_data');
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent();
            $table->timestamp('expires_at')->nullable();
            
            // Create a unique index on the hashed points
            $table->unique('hashed_points');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('route_cache');
    }
};
