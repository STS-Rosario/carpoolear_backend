<?php

namespace Tests\Feature\Http;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Mockery;
use STS\Http\Controllers\Api\v1\SupportTicketController;
use STS\Models\SupportTicket;
use STS\Models\SupportTicketAttachment;
use STS\Models\SupportTicketReply;
use STS\Models\User;
use STS\Services\SupportTicketService;
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

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_constructor_registers_logged_middleware(): void
    {
        $controller = new SupportTicketController(
            Mockery::mock(SupportTicketService::class),
        );

        $logged = collect($controller->getMiddleware())->first(function ($entry) {
            return (is_array($entry) ? ($entry['middleware'] ?? null) : ($entry->middleware ?? null)) === 'logged';
        });

        $this->assertNotNull($logged);
    }

    public function test_support_ticket_routes_require_authentication(): void
    {
        $checks = [
            ['GET', '/api/support/tickets', []],
            ['POST', '/api/support/tickets', []],
            ['GET', '/api/support/tickets/1', []],
            ['POST', '/api/support/tickets/1/replies', ['message_markdown' => 'Hello']],
            ['POST', '/api/support/tickets/1/close', []],
        ];

        foreach ($checks as [$method, $uri, $payload]) {
            $this->json($method, $uri, $payload)
                ->assertUnauthorized()
                ->assertJsonPath('message', 'Unauthorized.');
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

    public function test_index_lists_only_current_user_tickets_newest_first(): void
    {
        $owner = $this->createUser();
        $stranger = $this->createUser();

        $this->actingAs($owner, 'api');
        $firstId = (int) data_get($this->post('api/support/tickets', [
            'type' => 'contact',
            'subject' => 'First ticket',
            'message_markdown' => 'Hello',
        ])->json(), 'data.id');

        $secondId = (int) data_get($this->post('api/support/tickets', [
            'type' => 'contact',
            'subject' => 'Second ticket',
            'message_markdown' => 'Follow up',
        ])->json(), 'data.id');

        $this->actingAs($stranger, 'api');
        $this->post('api/support/tickets', [
            'type' => 'feedback',
            'subject' => 'Stranger only',
            'message_markdown' => 'Private',
        ])->assertStatus(200);

        $this->actingAs($owner, 'api');
        $response = $this->getJson('api/support/tickets');
        $response->assertStatus(200)->assertJsonCount(2, 'data');
        $this->assertSame($secondId, $response->json('data.0.id'));
        $this->assertSame($firstId, $response->json('data.1.id'));
    }

    public function test_show_returns_404_for_unknown_ticket_id(): void
    {
        $user = $this->createUser();
        $this->actingAs($user, 'api');

        $missingId = (int) (SupportTicket::query()->max('id') ?? 0) + 99_999;

        $this->getJson("api/support/tickets/{$missingId}")
            ->assertNotFound()
            ->assertExactJson(['error' => 'Ticket not found']);
    }

    public function test_show_returns_replies_with_nested_attachments_when_present(): void
    {
        $user = $this->createUser();
        $this->actingAs($user, 'api');

        $ticketId = (int) data_get($this->post('api/support/tickets', [
            'type' => 'contact',
            'subject' => 'Show nested',
            'message_markdown' => 'First message',
        ])->json(), 'data.id');

        $reply = SupportTicketReply::query()->where('ticket_id', $ticketId)->firstOrFail();
        SupportTicketAttachment::query()->create([
            'ticket_id' => null,
            'reply_id' => $reply->id,
            'user_id' => $user->id,
            'path' => 'support/2026/01/test.bin',
            'original_name' => 'shot.png',
            'mime' => 'image/png',
            'size_bytes' => 120,
        ]);

        $this->getJson("api/support/tickets/{$ticketId}")
            ->assertOk()
            ->assertJsonPath('data.id', $ticketId)
            ->assertJsonCount(1, 'data.replies')
            ->assertJsonCount(1, 'data.replies.0.attachments')
            ->assertJsonPath('data.replies.0.attachments.0.original_name', 'shot.png');
    }

    public function test_reply_returns_not_found_for_missing_ticket(): void
    {
        $user = $this->createUser();
        $this->actingAs($user, 'api');

        $missingId = (int) (SupportTicket::query()->max('id') ?? 0) + 99_999;

        $this->postJson("api/support/tickets/{$missingId}/replies", [
            'message_markdown' => 'Hello',
        ])
            ->assertNotFound()
            ->assertExactJson(['error' => 'Ticket not found']);
    }

    public function test_close_returns_not_found_for_missing_ticket(): void
    {
        $user = $this->createUser();
        $this->actingAs($user, 'api');

        $missingId = (int) (SupportTicket::query()->max('id') ?? 0) + 99_999;

        $this->postJson("api/support/tickets/{$missingId}/close", [])
            ->assertNotFound()
            ->assertExactJson(['error' => 'Ticket not found']);
    }

    public function test_reply_returns_422_when_ticket_is_resolved(): void
    {
        $user = $this->createUser();
        $this->actingAs($user, 'api');

        $ticketId = (int) data_get($this->post('api/support/tickets', [
            'type' => 'contact',
            'subject' => 'Needs closure test',
            'message_markdown' => 'Body',
        ])->json(), 'data.id');

        SupportTicket::query()->whereKey($ticketId)->update(['status' => 'Resuelto']);

        $this->postJson("api/support/tickets/{$ticketId}/replies", [
            'message_markdown' => 'Trying after resolve',
        ])
            ->assertStatus(422)
            ->assertExactJson(['error' => 'Ticket is closed for replies']);
    }

    public function test_reply_returns_422_when_ticket_is_closed(): void
    {
        $user = $this->createUser();
        $this->actingAs($user, 'api');

        $ticketId = (int) data_get($this->post('api/support/tickets', [
            'type' => 'contact',
            'subject' => 'Closed ticket',
            'message_markdown' => 'Body',
        ])->json(), 'data.id');

        SupportTicket::query()->whereKey($ticketId)->update(['status' => 'Cerrado']);

        $this->postJson("api/support/tickets/{$ticketId}/replies", [
            'message_markdown' => 'Trying after close',
        ])
            ->assertStatus(422)
            ->assertExactJson(['error' => 'Ticket is closed for replies']);
    }

    public function test_create_succeeds_when_attachments_key_is_omitted(): void
    {
        $user = $this->createUser();
        $this->actingAs($user, 'api');

        $this->post('api/support/tickets', [
            'type' => 'contact',
            'subject' => 'No attachment field',
            'message_markdown' => 'Plain text only',
        ])->assertStatus(200);
    }

    public function test_create_rejects_non_image_attachment_mime(): void
    {
        $user = $this->createUser();
        $this->actingAs($user, 'api');

        $this->postJson('api/support/tickets', [
            'type' => 'contact',
            'subject' => 'Bad file',
            'message_markdown' => 'See attach',
            'attachments' => [
                UploadedFile::fake()->create('notes.pdf', 12, 'application/pdf'),
            ],
        ])->assertStatus(422);
    }

    public function test_create_persists_created_by_and_last_reply_at(): void
    {
        $user = $this->createUser();
        $this->actingAs($user, 'api');

        $ticketId = (int) data_get($this->post('api/support/tickets', [
            'type' => 'feedback',
            'subject' => 'Audit fields',
            'message_markdown' => 'Hello',
        ])->json(), 'data.id');

        $ticket = SupportTicket::query()->findOrFail($ticketId);
        $this->assertSame($user->id, (int) $ticket->created_by);
        $this->assertNotNull($ticket->last_reply_at);
    }

    public function test_reply_persists_user_attachment_and_advances_ticket_status(): void
    {
        Storage::fake('public');

        $user = $this->createUser();
        $this->actingAs($user, 'api');

        $ticketId = (int) data_get($this->post('api/support/tickets', [
            'type' => 'contact',
            'subject' => 'Reply with file',
            'message_markdown' => 'Initial',
        ])->json(), 'data.id');

        $beforeFiles = count(Storage::disk('public')->allFiles());

        $this->post('api/support/tickets/'.$ticketId.'/replies', [
            'message_markdown' => 'Here is a screenshot',
            'attachments' => [
                UploadedFile::fake()->image('screen.png'),
            ],
        ])->assertStatus(200);

        $this->assertGreaterThan($beforeFiles, count(Storage::disk('public')->allFiles()));

        $this->assertDatabaseHas('support_ticket_replies', [
            'ticket_id' => $ticketId,
            'user_id' => $user->id,
            'is_admin' => 0,
            'message_markdown' => 'Here is a screenshot',
        ]);

        $replyIds = SupportTicketReply::query()->where('ticket_id', $ticketId)->pluck('id');
        $this->assertGreaterThan(0, SupportTicketAttachment::query()->whereIn('reply_id', $replyIds)->count());

        $this->assertSame('Esperando respuesta', SupportTicket::query()->findOrFail($ticketId)->status);
    }

    public function test_close_without_message_does_not_add_second_reply(): void
    {
        $user = $this->createUser();
        $this->actingAs($user, 'api');

        $ticketId = (int) data_get($this->post('api/support/tickets', [
            'type' => 'contact',
            'subject' => 'Close quietly',
            'message_markdown' => 'Only opening reply',
        ])->json(), 'data.id');

        $this->assertSame(1, SupportTicketReply::query()->where('ticket_id', $ticketId)->count());

        $this->postJson("api/support/tickets/{$ticketId}/close", [])
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'Cerrado');

        $this->assertSame(1, SupportTicketReply::query()->where('ticket_id', $ticketId)->count());
    }

    public function test_close_persists_status_and_optional_closing_message(): void
    {
        Storage::fake('public');
        $user = $this->createUser();
        $this->actingAs($user, 'api');

        $ticketId = (int) data_get($this->post('api/support/tickets', [
            'type' => 'contact',
            'subject' => 'Close me',
            'message_markdown' => 'Initial',
        ])->json(), 'data.id');

        $this->postJson("api/support/tickets/{$ticketId}/close", [
            'message_markdown' => 'Thanks, closing now.',
        ])->assertStatus(200)->assertJsonPath('data.status', 'Cerrado');

        $ticket = SupportTicket::query()->findOrFail($ticketId);
        $this->assertSame('Cerrado', $ticket->status);
        $this->assertSame($user->id, (int) $ticket->closed_by);
        $this->assertNotNull($ticket->closed_at);

        $closingReply = SupportTicketReply::query()
            ->where('ticket_id', $ticketId)
            ->where('message_markdown', 'Thanks, closing now.')
            ->first();
        $this->assertNotNull($closingReply);
        $this->assertFalse((bool) $closingReply->is_admin);
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

        $contactTicket = $this->post('api/support/tickets', [
            'type' => 'contact',
            'subject' => 'General contact',
            'message_markdown' => 'Question',
            'priority' => 'high',
        ]);
        $contactTicket->assertStatus(200);
        $contactTicket->assertJsonPath('data.priority', 'normal');
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
