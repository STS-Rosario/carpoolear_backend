<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('manual_identity_validations', function (Blueprint $table) {
            $table->string('reject_reason', 64)->nullable()->after('review_note');
        });
    }

    public function down(): void
    {
        Schema::table('manual_identity_validations', function (Blueprint $table) {
            $table->dropColumn('reject_reason');
        });
    }
};
