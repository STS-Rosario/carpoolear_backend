<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mercado_pago_rejected_validations', function (Blueprint $table) {
            $table->text('private_admin_note')->nullable()->after('review_note');
        });
    }

    public function down(): void
    {
        Schema::table('mercado_pago_rejected_validations', function (Blueprint $table) {
            $table->dropColumn('private_admin_note');
        });
    }
};
