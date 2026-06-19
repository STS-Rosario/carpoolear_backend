<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('manual_identity_validations', function (Blueprint $table) {
            $table->unsignedTinyInteger('submission_count')->default(0)->after('submitted_at');
        });

        DB::table('manual_identity_validations')
            ->whereNotNull('submitted_at')
            ->update(['submission_count' => 1]);
    }

    public function down(): void
    {
        Schema::table('manual_identity_validations', function (Blueprint $table) {
            $table->dropColumn('submission_count');
        });
    }
};
