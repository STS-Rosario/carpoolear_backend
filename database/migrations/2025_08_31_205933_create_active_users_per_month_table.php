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
        Schema::create('active_users_per_month', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('year');
            $table->unsignedInteger('month');
            $table->timestamp('saved_at');
            $table->unsignedInteger('value');
            $table->timestamps();
            
            // Add unique constraint to prevent duplicate entries for the same year/month
            $table->unique(['year', 'month']);
            
            // Add indexes for better query performance
            $table->index(['year', 'month']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('active_users_per_month');
    }
};
