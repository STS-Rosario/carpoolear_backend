<?php

namespace Tests\Feature\Http;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use STS\Models\User;
use Tests\TestCase;

class SupportTicketApiTest extends TestCase
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
        if (! Schema::hasTable('support_ticket_replies')) {
            Schema::create('support_ticket_replies', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('ticket_id');
                $table->unsignedInteger('user_id');
                $table->boolean('is_admin')->default(false);
                $table->text('message_markdown');
                $table->unsignedInteger('created_by')->nullable();
                $table->timestamps();
            });
        }
        if (! Schema::hasTable('support_ticket_attachments')) {
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
            });
        }
    }

    public function test_user_creates_ticket_with_attachments_and_admin_reply_updates_unread_counters(): void
    {
        Storage::fake('public');

        $user = $this->createUser();
        $admin = $this->createUser(true);

        $this->actingAs($user, 'api');
        $createResponse = $this->post('api/support/tickets', [
            'type' => 'bug_report',
            'subject' => 'App crash',
            'message_markdown' => 'Steps to reproduce',
            'attachments' => [
                UploadedFile::fake()->image('one.jpg'),
                UploadedFile::fake()->image('two.jpg'),
            ],
        ]);

        $createResponse->assertStatus(200);
        $ticketId = (int) data_get($createResponse->json(), 'data.id');
        $this->assertGreaterThan(0, $ticketId);
        $this->assertSame('Open', data_get($createResponse->json(), 'data.status'));
        $this->assertSame('normal', data_get($createResponse->json(), 'data.priority'));
        $this->assertSame(0, data_get($createResponse->json(), 'data.unread_for_user'));
        $this->assertSame(1, data_get($createResponse->json(), 'data.unread_for_admin'));

        $this->actingAs($admin, 'api');
        $this->withoutMiddleware(\STS\Http\Middleware\UserAdmin::class);
        $adminReply = $this->post("api/admin/support/tickets/{$ticketId}/replies", [
            'message_markdown' => 'We are reviewing it.',
        ]);
        $adminReply->assertStatus(200);
        $adminReply->assertJsonPath('data.status', 'En revision');
        $adminReply->assertJsonPath('data.unread_for_user', 1);
        $adminReply->assertJsonPath('data.unread_for_admin', 0);
    }

    public function test_status_actions_and_visibility_constraints(): void
    {
        $owner = $this->createUser();
        $otherUser = $this->createUser();
        $admin = $this->createUser(true);

        $this->actingAs($owner, 'api');
        $created = $this->post('api/support/tickets', [
            'type' => 'feedback',
            'subject' => 'Nice feature',
            'message_markdown' => 'Thank you team',
        ]);
        $created->assertStatus(200);
        $ticketId = (int) data_get($created->json(), 'data.id');

        $this->actingAs($otherUser, 'api');
        $this->get("api/support/tickets/{$ticketId}")->assertStatus(404);

        $this->actingAs($admin, 'api');
        $this->withoutMiddleware(\STS\Http\Middleware\UserAdmin::class);
        $this->post("api/admin/support/tickets/{$ticketId}/resolve", [
            'message_markdown' => 'Resolved in latest release.',
        ])->assertStatus(200)->assertJsonPath('data.status', 'Resuelto');

        $this->post("api/admin/support/tickets/{$ticketId}/close", [
            'message_markdown' => 'Closing ticket.',
        ])->assertStatus(200)->assertJsonPath('data.status', 'Cerrado');

        $this->post("api/admin/support/tickets/{$ticketId}/reopen")
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'En revision');
    }

    public function test_ticket_rate_limit_is_applied(): void
    {
        $user = $this->createUser();
        $this->actingAs($user, 'api');

        config()->set('carpoolear.support_ticket_rate_limit_create_per_hour', 1);

        $this->post('api/support/tickets', [
            'type' => 'contact',
            'subject' => 'Need help',
            'message_markdown' => 'First',
        ])->assertStatus(200);

        $this->post('api/support/tickets', [
            'type' => 'contact',
            'subject' => 'Need more help',
            'message_markdown' => 'Second',
        ])->assertStatus(429);
    }

    public function test_priority_is_assigned_by_type_and_not_by_user_input(): void
    {
        $user = $this->createUser();
        $this->actingAs($user, 'api');

        $reportTicket = $this->post('api/support/tickets', [
            'type' => 'report',
            'subject' => 'Bad behavior',
            'message_markdown' => 'Needs moderation',
            'priority' => 'low',
        ]);
        $reportTicket->assertStatus(200);
        $reportTicket->assertJsonPath('data.priority', 'high');

        $bugTicket = $this->post('api/support/tickets', [
            'type' => 'bug_report',
            'subject' => 'Bug found',
            'message_markdown' => 'Steps...',
            'priority' => 'high',
        ]);
        $bugTicket->assertStatus(200);
        $bugTicket->assertJsonPath('data.priority', 'normal');

        $feedbackTicket = $this->post('api/support/tickets', [
            'type' => 'feedback',
            'subject' => 'Suggestion',
            'message_markdown' => 'Could improve this',
            'priority' => 'high',
        ]);
        $feedbackTicket->assertStatus(200);
        $feedbackTicket->assertJsonPath('data.priority', 'low');
    }

    private function createUser(bool $isAdmin = false): User
    {
        return User::query()->create([
            'name' => 'Support Tester '.uniqid(),
            'email' => uniqid('support_', true).'@example.com',
            'password' => Hash::make('123456'),
            'active' => 1,
            'is_admin' => $isAdmin ? 1 : 0,
            'terms_and_conditions' => 1,
            'emails_notifications' => 1,
        ]);
    }
}
