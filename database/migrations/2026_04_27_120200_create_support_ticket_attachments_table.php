<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_ticket_attachments', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('ticket_id')->nullable();
            $table->unsignedInteger('reply_id')->nullable();
            $table->unsignedInteger('user_id');
            $table->string('path');
            $table->string('original_name');
            $table->string('mime', 120);
            $table->unsignedInteger('size_bytes')->default(0);
            $table->timestamps();

            $table->foreign('ticket_id')->references('id')->on('support_tickets')->onDelete('cascade');
            $table->foreign('reply_id')->references('id')->on('support_ticket_replies')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });

        DB::statement(
            'ALTER TABLE support_ticket_attachments ADD CONSTRAINT support_ticket_attachments_single_parent CHECK ((ticket_id IS NOT NULL AND reply_id IS NULL) OR (ticket_id IS NULL AND reply_id IS NOT NULL))'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('support_ticket_attachments');
    }
};
