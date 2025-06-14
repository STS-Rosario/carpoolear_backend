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
        Schema::create('campaign_donations', function (Blueprint $table) {
            $table->id();
            $table
                ->foreignId('campaign_id')
                ->constrained('campaigns')
                ->onDelete('cascade');
            $table->string('payment_id')->nullable(); // Mercado Pago preference/payment ID
            $table->integer('amount_cents');
            $table->string('name')->nullable();
            $table->text('comment')->nullable();
            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users');
            $table->enum('status', ['pending', 'paid', 'failed'])->default('pending');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('campaign_donations');
    }
};
