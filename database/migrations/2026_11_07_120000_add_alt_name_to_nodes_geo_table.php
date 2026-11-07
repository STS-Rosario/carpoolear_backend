<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('nodes_geo', function (Blueprint $table) {
            $table->string('alt_name')->nullable()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('nodes_geo', function (Blueprint $table) {
            $table->dropColumn('alt_name');
        });
    }
};
