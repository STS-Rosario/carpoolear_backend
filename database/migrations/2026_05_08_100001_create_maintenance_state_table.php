<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maintenance_state', function (Blueprint $table) {
            $table->unsignedTinyInteger('id')->primary();
            $table->boolean('is_active')->default(false);
            $table->string('mode', 16)->nullable();
            $table->text('message')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->string('source', 16)->nullable();
            $table->foreignId('active_schedule_id')->nullable()->constrained('maintenance_schedules')->nullOnDelete();
            $table->timestamps();
        });

        DB::table('maintenance_state')->insert([
            'id' => 1,
            'is_active' => false,
            'mode' => null,
            'message' => null,
            'ends_at' => null,
            'source' => null,
            'active_schedule_id' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_state');
    }
};
