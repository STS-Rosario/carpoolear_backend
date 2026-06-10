<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('car_models', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->increments('id');
            $table->unsignedInteger('car_brand_id');
            $table->string('name', 150);
            $table->string('slug', 170);
            $table->unsignedInteger('argautos_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('car_brand_id')->references('id')->on('car_brands')
                ->onDelete('cascade')->onUpdate('cascade');
            $table->unique(['car_brand_id', 'slug']);
            $table->unique(['car_brand_id', 'argautos_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('car_models');
    }
};
