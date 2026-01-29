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
        Schema::table('mercado_pago_rejected_validations', function (Blueprint $table) {
            $table->timestamp('approved_at')->nullable()->after('mp_payload');
            $table->unsignedInteger('approved_by')->nullable()->after('approved_at');
            $table->foreign('approved_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mercado_pago_rejected_validations', function (Blueprint $table) {
            $table->dropForeign(['approved_by']);
            $table->dropColumn(['approved_at', 'approved_by']);
        });
    }
};
