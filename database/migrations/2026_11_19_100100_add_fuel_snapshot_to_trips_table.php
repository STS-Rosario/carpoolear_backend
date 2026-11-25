<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trips', function (Blueprint $table) {
            $table->string('fuel_type', 32)->nullable();
            $table->string('fuel_consumption', 16)->nullable();
            $table->decimal('fuel_price_per_unit', 12, 4)->nullable();
            $table->decimal('fuel_consumption_per_100', 8, 2)->nullable();
            $table->string('fuel_unit', 8)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('trips', function (Blueprint $table) {
            $table->dropColumn([
                'fuel_type',
                'fuel_consumption',
                'fuel_price_per_unit',
                'fuel_consumption_per_100',
                'fuel_unit',
            ]);
        });
    }
};
