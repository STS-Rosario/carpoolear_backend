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
        Schema::create('campaign_rewards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained()->onDelete('cascade');
            $table->string('title');
            $table->text('description');
            $table->integer('donation_amount_cents');
            $table->integer('quantity_available')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Add campaign_reward_id to campaign_donations table
        Schema::table('campaign_donations', function (Blueprint $table) {
            $table->foreignId('campaign_reward_id')->nullable()->constrained()->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('campaign_donations', function (Blueprint $table) {
            $table->dropForeign(['campaign_reward_id']);
            $table->dropColumn('campaign_reward_id');
        });
        
        Schema::dropIfExists('campaign_rewards');//
    }
};
