<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('identity_verification_events', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('user_id')->nullable();
            $table->string('method', 32);
            $table->string('name', 64);
            $table->string('reason', 64)->nullable();
            $table->uuid('attempt_id')->nullable();
            $table->string('related_type', 64)->nullable();
            $table->unsignedBigInteger('related_id')->nullable();
            $table->string('surface', 64)->nullable();
            $table->string('platform', 32)->nullable();
            $table->string('app_version', 64)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            $table->index(['name', 'created_at']);
            $table->index(['method', 'reason', 'created_at']);
            $table->index(['user_id', 'created_at']);
            $table->index('attempt_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('identity_verification_events');
    }
};
