<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users_references', function (Blueprint $table) {
            $table->text('reply_comment')->nullable();
            $table->dateTime('reply_comment_created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users_references', function (Blueprint $table) {
            $table->dropColumn(['reply_comment', 'reply_comment_created_at']);
        });
    }
};
