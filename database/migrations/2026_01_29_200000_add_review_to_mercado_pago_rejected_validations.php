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
            $table->string('review_status', 20)->nullable()->after('approved_by'); // approved, rejected, pending
            $table->text('review_note')->nullable()->after('review_status');
            $table->timestamp('reviewed_at')->nullable()->after('review_note');
            $table->unsignedInteger('reviewed_by')->nullable()->after('reviewed_at');
            $table->foreign('reviewed_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mercado_pago_rejected_validations', function (Blueprint $table) {
            $table->dropForeign(['reviewed_by']);
            $table->dropColumn(['review_status', 'review_note', 'reviewed_at', 'reviewed_by']);
        });
    }
};
