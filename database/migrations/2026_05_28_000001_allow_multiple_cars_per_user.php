<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cars', function (Blueprint $table) {
            $table->index('user_id', 'cars_user_id_index');
            $table->dropUnique('cars_user_id_unique');
        });
    }

    public function down(): void
    {
        Schema::table('cars', function (Blueprint $table) {
            $table->unique('user_id', 'cars_user_id_unique');
            $table->dropIndex('cars_user_id_index');
        });
    }
};
