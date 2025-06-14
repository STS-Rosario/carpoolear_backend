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
        Schema::create('campaigns', function (Blueprint $table) {
            $table->unsignedInteger('id')->autoIncrement();
            $table->string('slug')->unique(); // friendly URL
            $table->string('title');
            $table->text('description');
            $table->string('image_path')->nullable();
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->string('payment_slug')->nullable(); // optional field for Mercado Pago external reference
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('campaign_donations');
        Schema::dropIfExists('campaign_milestones');
        Schema::dropIfExists('campaigns');
    }
};
