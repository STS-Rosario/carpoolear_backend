<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trip_passengers', function (Blueprint $table) {
            $table->unsignedInteger('message_id')->nullable()->unique()->after('canceled_state');
            $table->foreign('message_id')->references('id')->on('messages')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('trip_passengers', function (Blueprint $table) {
            $table->dropForeign(['message_id']);
            $table->dropUnique(['message_id']);
            $table->dropColumn('message_id');
        });
    }
};
