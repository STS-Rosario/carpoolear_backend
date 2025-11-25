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
        Schema::create('delete_account_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('user_id');
            $table->dateTime('date_requested');
            $table->unsignedInteger('action_taken')->default(0); // 0=REQUESTED, 1=DELETED, 2=REJECTED
            $table->dateTime('action_taken_date')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')
                  ->onDelete('cascade')
                  ->onUpdate('cascade');
            
            $table->index('user_id');
            $table->index('action_taken');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('delete_account_requests');
    }
};
