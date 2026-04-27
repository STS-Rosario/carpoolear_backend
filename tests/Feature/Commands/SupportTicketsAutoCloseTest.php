<?php

namespace Tests\Feature\Commands;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use STS\Models\SupportTicket;
use STS\Models\User;
use Tests\TestCase;

class SupportTicketsAutoCloseTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasTable('support_tickets')) {
            Schema::create('support_tickets', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('user_id');
                $table->string('type');
                $table->string('subject');
                $table->string('status')->default('Open');
                $table->string('priority')->default('normal');
                $table->unsignedInteger('unread_for_user')->default(0);
                $table->unsignedInteger('unread_for_admin')->default(0);
                $table->text('internal_note_markdown')->nullable();
                $table->timestamp('last_reply_at')->nullable();
                $table->unsignedInteger('created_by')->nullable();
                $table->unsignedInteger('updated_by')->nullable();
                $table->unsignedInteger('closed_by')->nullable();
                $table->timestamp('closed_at')->nullable();
                $table->timestamps();
            });
        }
    }

    public function test_command_closes_old_resolved_tickets(): void
    {
        config()->set('carpoolear.support_ticket_autoclose_days', 10);
        $user = User::query()->create([
            'name' => 'Auto Close',
            'email' => uniqid('autoclose_', true).'@example.com',
            'password' => bcrypt('123456'),
            'active' => 1,
            'is_admin' => 0,
            'terms_and_conditions' => 1,
            'emails_notifications' => 1,
        ]);

        $ticket = SupportTicket::create([
            'user_id' => $user->id,
            'type' => 'feedback',
            'subject' => 'Old resolved',
            'status' => 'Resuelto',
            'last_reply_at' => now()->subDays(11),
            'created_by' => $user->id,
        ]);

        $this->artisan('support-tickets:autoclose')->assertExitCode(0);

        $ticket->refresh();
        $this->assertSame('Cerrado', $ticket->status);
        $this->assertNotNull($ticket->closed_at);
    }
}
