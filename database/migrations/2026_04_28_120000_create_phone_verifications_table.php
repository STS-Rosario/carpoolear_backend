<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('phone_verifications', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->increments('id');
            $table->integer('user_id')->unsigned();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->string('phone_number', 32);
            $table->boolean('verified')->default(false);
            $table->string('verification_code', 16)->nullable();
            $table->timestamp('code_sent_at')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->unsignedSmallInteger('failed_attempts')->default(0);
            $table->unsignedSmallInteger('resend_count')->default(0);
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'verified', 'created_at']);
            $table->index(['phone_number', 'verified']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('phone_verifications');
    }
};
