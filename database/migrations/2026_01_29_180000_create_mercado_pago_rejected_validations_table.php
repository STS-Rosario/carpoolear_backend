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
        Schema::create('mercado_pago_rejected_validations', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('user_id');
            $table->string('reject_reason', 64); // e.g. dni_mismatch, name_mismatch
            $table->json('mp_payload'); // full /users/me response for admin review
            $table->timestamps();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mercado_pago_rejected_validations');
    }
};
