<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('donation_tiers', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 64)->unique();
            $table->string('label_key', 128);
            $table->unsignedInteger('amount_cents');
            $table->string('icon', 64)->default('fa-heart');
            $table->string('mp_preapproval_plan_id', 128)->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamp('effective_from')->nullable();
            $table->timestamps();
        });

        Schema::create('donation_payments', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('user_id')->nullable();
            $table->foreignId('donation_tier_id')->nullable()->constrained('donation_tiers')->nullOnDelete();
            $table->unsignedInteger('amount_cents');
            $table->string('currency', 8)->default('ARS');
            $table->enum('status', ['pending', 'approved', 'rejected', 'refunded'])->default('pending');
            $table->string('mp_payment_id', 64)->nullable()->unique();
            $table->string('mp_preference_id', 64)->nullable();
            $table->text('external_reference')->nullable();
            $table->string('source', 64)->nullable();
            $table->unsignedInteger('trip_id')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            $table->index(['user_id', 'status']);
        });

        Schema::create('donation_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('user_id')->nullable();
            $table->foreignId('donation_tier_id')->nullable()->constrained('donation_tiers')->nullOnDelete();
            $table->string('mp_preapproval_id', 64)->nullable()->unique();
            $table->string('mp_preapproval_plan_id', 128)->nullable();
            $table->enum('status', ['pending', 'authorized', 'paused', 'cancelled'])->default('pending');
            $table->unsignedInteger('transaction_amount_cents');
            $table->date('next_payment_date')->nullable();
            $table->timestamp('last_charged_at')->nullable();
            $table->text('external_reference')->nullable();
            $table->string('source', 64)->nullable();
            $table->unsignedInteger('trip_id')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            $table->index(['user_id', 'status']);
        });

        Schema::create('donation_subscription_charges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('donation_subscription_id')->constrained('donation_subscriptions')->cascadeOnDelete();
            $table->string('mp_payment_id', 64)->unique();
            $table->unsignedInteger('amount_cents');
            $table->enum('status', ['pending', 'approved', 'rejected', 'refunded'])->default('pending');
            $table->timestamp('charged_at')->nullable();
            $table->timestamps();
        });

        Schema::create('donation_amount_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('donation_tier_id')->constrained('donation_tiers')->cascadeOnDelete();
            $table->unsignedInteger('old_amount_cents');
            $table->unsignedInteger('new_amount_cents');
            $table->unsignedInteger('admin_user_id')->nullable();
            $table->unsignedInteger('subscriptions_updated')->default(0);
            $table->unsignedInteger('subscriptions_failed')->default(0);
            $table->timestamp('applied_at')->nullable();
            $table->timestamps();

            $table->foreign('admin_user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('donation_amount_adjustments');
        Schema::dropIfExists('donation_subscription_charges');
        Schema::dropIfExists('donation_subscriptions');
        Schema::dropIfExists('donation_payments');
        Schema::dropIfExists('donation_tiers');
    }
};
