<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('support_tickets', function (Blueprint $table) {
            $table->unsignedInteger('trip_id')->nullable()->after('source');
            $table->index(['trip_id', 'type']);
            $table->foreign('trip_id')->references('id')->on('trips')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('support_tickets', function (Blueprint $table) {
            $table->dropForeign(['trip_id']);
            $table->dropIndex(['trip_id', 'type']);
            $table->dropColumn('trip_id');
        });
    }
};
