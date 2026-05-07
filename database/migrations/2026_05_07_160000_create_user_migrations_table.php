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
        Schema::create('user_migrations', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('admin_user_id');
            $table->unsignedInteger('user_id_kept');
            $table->unsignedInteger('user_id_removed');
            $table->timestamps();

            $table->index('admin_user_id');
            $table->index('user_id_kept');
            $table->index('user_id_removed');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_migrations');
    }
};
