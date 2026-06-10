<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cars', function (Blueprint $table) {
            $table->unsignedInteger('car_brand_id')->nullable()->after('description');
            $table->unsignedInteger('car_model_id')->nullable()->after('car_brand_id');
            $table->string('brand_other', 100)->nullable()->after('car_model_id');
            $table->string('model_other', 100)->nullable()->after('brand_other');
            $table->unsignedInteger('car_color_id')->nullable()->after('model_other');

            $table->foreign('car_brand_id')->references('id')->on('car_brands')
                ->nullOnDelete()->cascadeOnUpdate();
            $table->foreign('car_model_id')->references('id')->on('car_models')
                ->nullOnDelete()->cascadeOnUpdate();
            $table->foreign('car_color_id')->references('id')->on('car_colors')
                ->nullOnDelete()->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::table('cars', function (Blueprint $table) {
            $table->dropForeign(['car_brand_id']);
            $table->dropForeign(['car_model_id']);
            $table->dropForeign(['car_color_id']);
            $table->dropColumn([
                'car_brand_id',
                'car_model_id',
                'brand_other',
                'model_other',
                'car_color_id',
            ]);
        });
    }
};
