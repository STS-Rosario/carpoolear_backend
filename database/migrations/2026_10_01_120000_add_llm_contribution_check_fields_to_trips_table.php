<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trips', function (Blueprint $table) {
            // Per-person amount the LLM contribution check found in the description (currency units).
            $table->decimal('suspected_contribution', 12, 2)->nullable()->after('description_potential_seat_price_cents');
            $table->boolean('phone_in_description')->default(false)->after('suspected_contribution');
        });
    }

    public function down(): void
    {
        Schema::table('trips', function (Blueprint $table) {
            $table->dropColumn(['suspected_contribution', 'phone_in_description']);
        });
    }
};
