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
        Schema::create('admin_action_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('admin_user_id');
            $table->string('action', 50);
            $table->unsignedInteger('target_user_id');
            $table->json('details')->nullable();
            $table->timestamps();

            $table->index('admin_user_id');
            $table->index('target_user_id');
            $table->index('action');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('admin_action_logs');
    }
};
