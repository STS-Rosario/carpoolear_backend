<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trip_live_shares', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('trip_id');
            $table->unsignedInteger('user_id');
            $table->string('share_token', 64)->unique();
            $table->boolean('is_active')->default(true);
            $table->double('lat')->nullable();
            $table->double('lng')->nullable();
            $table->timestamp('recorded_at')->nullable();
            $table->timestamp('stop_reminder_sent_at')->nullable();
            $table->timestamp('auto_stopped_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('stopped_at')->nullable();
            $table->timestamps();

            $table->foreign('trip_id')->references('id')->on('trips')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->unique(['trip_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trip_live_shares');
    }
};
