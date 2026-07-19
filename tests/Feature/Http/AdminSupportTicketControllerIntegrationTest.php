<?php

namespace Tests\Feature\Http;

use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Mockery;
use STS\Http\Middleware\UserAdmin;
use STS\Models\SupportTicket;
use STS\Models\SupportTicketAttachment;
use STS\Models\SupportTicketReply;
use STS\Models\User;
use STS\Notifications\SupportTicketReplyNotification;
use STS\Services\Notifications\NotificationServices;
use Tests\TestCase;

class AdminSupportTicketControllerIntegrationTest extends TestCase
{
    private function adminUser(): User
    {
        $user = User::factory()->create();
        $user->forceFill(['is_admin' => true])->saveQuietly();

        return $user->fresh();
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeTicket(User $owner, array $overrides = []): SupportTicket
    {
        return SupportTicket::create(array_merge([
            'user_id' => $owner->id,
            'type' => 'feedback',
            'subject' => 'Subject '.uniqid('', true),
            'status' => 'Open',
            'priority' => 'normal',
            'unread_for_user' => 0,
            'unread_for_admin' => 0,
        ], $overrides));
    }

    public function test_index_filters_by_type_when_type_query_param_is_set(): void
    {
        $admin = $this->adminUser();
        $owner = User::factory()->create();
        $feedback = $this->makeTicket($owner, ['type' => 'feedback', 'subject' => 'feedback-only']);
        $this->makeTicket($owner, ['type' => 'bug_report', 'subject' => 'bug-only']);

        $this->actingAs($admin, 'api');
        $this->withoutMiddleware(UserAdmin::class);

        $rows = collect($this->getJson('api/admin/support/tickets?type=feedback')->assertOk()->json('data'));
        $ids = $rows->pluck('id')->map(fn ($id) => (int) $id)->all();

        $this->assertContains($feedback->id, $ids);
        $this->assertTrue($rows->every(fn (array $row): bool => $row['type'] === 'feedback'));
    }

    public function test_index_filters_by_priority_when_priority_query_param_is_set(): void
    {
        $admin = $this->adminUser();
        $owner = User::factory()->create();
        $high = $this->makeTicket($owner, ['priority' => 'high', 'subject' => 'high-only']);
        $this->makeTicket($owner, ['priority' => 'low', 'subject' => 'low-only']);

        $this->actingAs($admin, 'api');
        $this->withoutMiddleware(UserAdmin::class);

        $rows = collect($this->getJson('api/admin/support/tickets?priority=high')->assertOk()->json('data'));

        $this->assertContains($high->id, $rows->pluck('id')->map(fn ($id) => (int) $id)->all());
        $this->assertTrue($rows->every(fn (array $row): bool => $row['priority'] === 'high'));
    }

    public function test_index_filters_by_needs_reply_when_query_param_is_set(): void
    {
        $admin = $this->adminUser();
        $owner = User::factory()->create();
        $needsAttention = $this->makeTicket($owner, [
            'status' => SupportTicket::STATUS_NEEDS_REVIEW,
            'unread_for_admin' => 0,
            'subject' => 'needs-attention',
        ]);
        $this->makeTicket($owner, [
            'status' => 'Esperando respuesta',
            'unread_for_admin' => 0,
            'subject' => 'waiting-user',
        ]);

        $this->actingAs($admin, 'api');
        $this->withoutMiddleware(UserAdmin::class);

        $rows = collect($this->getJson('api/admin/support/tickets?needs_reply=1')->assertOk()->json('data'));
        $ids = $rows->pluck('id')->map(fn ($id) => (int) $id)->all();

        $this->assertContains($needsAttention->id, $ids);
        $this->assertTrue($rows->every(fn (array $row): bool => in_array($row['status'], ['Open', 'En revision', SupportTicket::STATUS_NEEDS_REVIEW], true)
            || (int) ($row['unread_for_admin'] ?? 0) > 0));
    }

    public function test_index_needs_reply_filter_excludes_closed_and_resolved_tickets(): void
    {
        $admin = $this->adminUser();
        $owner = User::factory()->create();
        $openNeedsReply = $this->makeTicket($owner, [
            'status' => 'Open',
            'unread_for_admin' => 1,
            'subject' => 'open-needs-reply',
        ]);
        $resolvedUnread = $this->makeTicket($owner, [
            'status' => 'Resuelto',
            'unread_for_admin' => 1,
            'subject' => 'resolved-unread',
        ]);
        $closedUnread = $this->makeTicket($owner, [
            'status' => 'Cerrado',
            'unread_for_admin' => 1,
            'subject' => 'closed-unread',
        ]);

        $this->actingAs($admin, 'api');
        $this->withoutMiddleware(UserAdmin::class);

        $rows = collect($this->getJson('api/admin/support/tickets?needs_reply=1')->assertOk()->json('data'));
        $ids = $rows->pluck('id')->map(fn ($id) => (int) $id)->all();

        $this->assertContains($openNeedsReply->id, $ids);
        $this->assertNotContains($resolvedUnread->id, $ids);
        $this->assertNotContains($closedUnread->id, $ids);
        $this->assertTrue($rows->every(fn (array $row): bool => ! in_array($row['status'], ['Resuelto', 'Cerrado'], true)));
    }

    public function test_index_filters_by_user_id_when_query_param_is_set(): void
    {
        $admin = $this->adminUser();
        $target = User::factory()->create();
        $other = User::factory()->create();
        $targetTicket = $this->makeTicket($target, ['subject' => 'target-user-ticket']);
        $this->makeTicket($other, ['subject' => 'other-user-ticket']);

        $this->actingAs($admin, 'api');
        $this->withoutMiddleware(UserAdmin::class);

        $rows = collect($this->getJson('api/admin/support/tickets?user_id='.$target->id)->assertOk()->json('data'));
        $ids = $rows->pluck('id')->map(fn ($id) => (int) $id)->all();

        $this->assertContains($targetTicket->id, $ids);
        $this->assertTrue($rows->every(fn (array $row): bool => (int) ($row['user_id'] ?? 0) === $target->id));
    }

    public function test_index_returns_data_ordered_newest_first(): void
    {
        $admin = $this->adminUser();
        $owner = User::factory()->create();
        $older = $this->makeTicket($owner, ['subject' => 'older']);
        $newer = $this->makeTicket($owner, ['subject' => 'newer']);

        $this->actingAs($admin, 'api');
        $this->withoutMiddleware(UserAdmin::class);

        $indexResponse = $this->getJson('api/admin/support/tickets')->assertOk();
        $this->assertSame(['data'], array_keys($indexResponse->json()));

        $rows = collect($indexResponse->json('data'));

        $this->assertGreaterThanOrEqual(2, $rows->count());
        $newerIdx = $rows->search(fn (array $r): bool => (int) $r['id'] === $newer->id);
        $olderIdx = $rows->search(fn (array $r): bool => (int) $r['id'] === $older->id);
        $this->assertNotFalse($newerIdx);
        $this->assertNotFalse($olderIdx);
        $this->assertLessThan($olderIdx, $newerIdx, 'Higher-id ticket should appear before lower-id (descending order)');
    }

    public function test_index_includes_ticket_owner_user_with_id_and_name(): void
    {
        $admin = $this->adminUser();
        $owner = User::factory()->create(['name' => 'Ticket Owner Person']);
        $ticket = $this->makeTicket($owner);

        $this->actingAs($admin, 'api');
        $this->withoutMiddleware(UserAdmin::class);

        $indexResponse = $this->getJson('api/admin/support/tickets')->assertOk();
        $row = collect($indexResponse->json('data'))->firstWhere('id', $ticket->id);

        $this->assertIsArray($row);
        $this->assertArrayHasKey('user', $row);
        $this->assertIsArray($row['user']);
        $this->assertSame($owner->id, (int) $row['user']['id']);
        $this->assertSame('Ticket Owner Person', $row['user']['name']);
    }

    public function test_show_includes_user_ticket_attachments_and_reply_attachments(): void
    {
        $admin = $this->adminUser();
        $owner = User::factory()->create();
        $ticket = $this->makeTicket($owner);

        SupportTicketAttachment::create([
            'ticket_id' => $ticket->id,
            'reply_id' => null,
            'user_id' => $owner->id,
            'path' => 'support/2026/01/ticket.png',
            'original_name' => 'ticket.png',
            'mime' => 'image/png',
            'size_bytes' => 400,
        ]);

        $userReply = SupportTicketReply::create([
            'ticket_id' => $ticket->id,
            'user_id' => $owner->id,
            'is_admin' => false,
            'message_markdown' => 'User says hello',
            'created_by' => $owner->id,
        ]);

        SupportTicketAttachment::create([
            'ticket_id' => null,
            'reply_id' => $userReply->id,
            'user_id' => $owner->id,
            'path' => 'support/2026/01/reply.jpg',
            'original_name' => 'reply.jpg',
            'mime' => 'image/jpeg',
            'size_bytes' => 800,
        ]);

        $this->actingAs($admin, 'api');
        $this->withoutMiddleware(UserAdmin::class);

        $showResponse = $this->getJson('api/admin/support/tickets/'.$ticket->id)->assertOk();
        $this->assertSame(['data'], array_keys($showResponse->json()));

        $payload = $showResponse->json('data');

        $this->assertSame($owner->id, $payload['user']['id']);
        $this->assertSame($owner->email, $payload['user']['email']);

        $ticketAttachmentNames = collect($payload['attachments'])->pluck('original_name')->all();
        $this->assertContains('ticket.png', $ticketAttachmentNames);

        $replyWithFile = collect($payload['replies'])->firstWhere('id', $userReply->id);
        $this->assertNotNull($replyWithFile);
        $this->assertSame('reply.jpg', $replyWithFile['attachments'][0]['original_name']);
    }

    public function test_show_includes_reply_user_with_id_and_name_for_admin_replies(): void
    {
        $admin = $this->adminUser();
        $owner = User::factory()->create();
        $ticket = $this->makeTicket($owner);

        SupportTicketReply::create([
            'ticket_id' => $ticket->id,
            'user_id' => $admin->id,
            'is_admin' => true,
            'message_markdown' => 'Respuesta del equipo',
            'created_by' => $admin->id,
        ]);

        $this->actingAs($admin, 'api');
        $this->withoutMiddleware(UserAdmin::class);

        $payload = $this->getJson('api/admin/support/tickets/'.$ticket->id)->assertOk()->json('data');
        $adminReply = collect($payload['replies'])->firstWhere('is_admin', true);
        $this->assertNotNull($adminReply);
        $this->assertIsArray($adminReply['user']);
        $this->assertSame($admin->id, $adminReply['user']['id']);
        $this->assertSame($admin->name, $adminReply['user']['name']);
    }

    public function test_reply_without_message_is_unprocessable(): void
    {
        Storage::fake('local');

        $admin = $this->adminUser();
        $owner = User::factory()->create();
        $ticket = $this->makeTicket($owner);

        $this->actingAs($admin, 'api');
        $this->withoutMiddleware(UserAdmin::class);

        $this->postJson('api/admin/support/tickets/'.$ticket->id.'/replies', [])
            ->assertUnprocessable();
    }

    public function test_reply_with_image_persists_attachment_for_reply(): void
    {
        Storage::fake('local');

        $admin = $this->adminUser();
        $owner = User::factory()->create();
        $ticket = $this->makeTicket($owner);

        $this->actingAs($admin, 'api');
        $this->withoutMiddleware(UserAdmin::class);

        $file = UploadedFile::fake()->image('note.png', 30, 30);

        $replyResponse = $this->post('api/admin/support/tickets/'.$ticket->id.'/replies', [
            'message_markdown' => 'Please see screenshot.',
            'attachments' => [$file],
        ])->assertOk();
        $this->assertSame(['data'], array_keys($replyResponse->json()));

        $reply = SupportTicketReply::query()
            ->where('ticket_id', $ticket->id)
            ->where('user_id', $admin->id)
            ->where('is_admin', true)
            ->latest('id')
            ->first();

        $this->assertNotNull($reply);
        $this->assertDatabaseHas('support_ticket_attachments', [
            'reply_id' => $reply->id,
            'user_id' => $admin->id,
            'original_name' => 'note.png',
        ]);
    }

    public function test_reply_with_text_only_returns_fresh_ticket_under_data_and_advances_status(): void
    {
        $admin = $this->adminUser();
        $owner = User::factory()->create();
        $ticket = $this->makeTicket($owner, ['status' => 'Open']);

        $this->actingAs($admin, 'api');
        $this->withoutMiddleware(UserAdmin::class);

        $response = $this->postJson('api/admin/support/tickets/'.$ticket->id.'/replies', [
            'message_markdown' => 'We are looking into this.',
        ])->assertOk();

        $this->assertSame(['data'], array_keys($response->json()));
        $this->assertSame($ticket->id, $response->json('data.id'));
        $this->assertSame('Esperando respuesta', $response->json('data.status'));

        $this->assertDatabaseHas('support_ticket_replies', [
            'ticket_id' => $ticket->id,
            'user_id' => $admin->id,
            'is_admin' => true,
            'message_markdown' => 'We are looking into this.',
            'created_by' => $admin->id,
        ]);
    }

    public function test_reply_returns_422_when_message_duplicates_existing_reply(): void
    {
        $admin = $this->adminUser();
        $owner = User::factory()->create();
        $ticket = $this->makeTicket($owner, ['status' => 'Open']);

        $duplicateBody = 'Exact duplicate message on this thread.';
        SupportTicketReply::create([
            'ticket_id' => $ticket->id,
            'user_id' => $owner->id,
            'is_admin' => false,
            'message_markdown' => $duplicateBody,
            'created_by' => $owner->id,
        ]);

        $this->actingAs($admin, 'api');
        $this->withoutMiddleware(UserAdmin::class);

        $this->postJson('api/admin/support/tickets/'.$ticket->id.'/replies', [
            'message_markdown' => $duplicateBody,
        ])
            ->assertStatus(422)
            ->assertExactJson(['error' => 'Duplicate reply']);

        $this->assertSame(1, SupportTicketReply::query()->where('ticket_id', $ticket->id)->count());
    }

    public function test_reply_returns_success_when_notification_delivery_fails(): void
    {
        $this->mock(NotificationServices::class, function ($mock) {
            $mock->shouldReceive('send')->andThrow(new \RuntimeException('delivery failed'));
        });

        $this->mock(ExceptionHandler::class, function ($mock) {
            $mock->shouldIgnoreMissing();
            $mock->shouldReceive('report')->once()->with(Mockery::type(\Throwable::class));
        });

        $admin = $this->adminUser();
        $owner = User::factory()->create();
        $ticket = $this->makeTicket($owner, ['status' => 'Open']);

        $this->actingAs($admin, 'api');
        $this->withoutMiddleware(UserAdmin::class);

        $response = $this->postJson('api/admin/support/tickets/'.$ticket->id.'/replies', [
            'message_markdown' => 'Answer posted regardless of push.',
        ])->assertOk();

        $this->assertSame(['data'], array_keys($response->json()));
        $this->assertSame('Esperando respuesta', $response->json('data.status'));
        $this->assertDatabaseHas('support_ticket_replies', [
            'ticket_id' => $ticket->id,
            'user_id' => $admin->id,
            'message_markdown' => 'Answer posted regardless of push.',
        ]);
    }

    public function test_reply_accepts_explicit_empty_attachments_array(): void
    {
        $admin = $this->adminUser();
        $owner = User::factory()->create();
        $ticket = $this->makeTicket($owner);

        $this->actingAs($admin, 'api');
        $this->withoutMiddleware(UserAdmin::class);

        $this->postJson('api/admin/support/tickets/'.$ticket->id.'/replies', [
            'message_markdown' => 'No files attached.',
            'attachments' => [],
        ])->assertOk();

        $this->assertDatabaseHas('support_ticket_replies', [
            'ticket_id' => $ticket->id,
            'user_id' => $admin->id,
            'message_markdown' => 'No files attached.',
        ]);
    }

    public function test_reopen_clears_closure_fields_and_sets_ticket_under_review(): void
    {
        $admin = $this->adminUser();
        $owner = User::factory()->create();
        $ticket = $this->makeTicket($owner, [
            'status' => 'Cerrado',
            'closed_at' => now()->subHour(),
            'closed_by' => $admin->id,
        ]);

        $this->actingAs($admin, 'api');
        $this->withoutMiddleware(UserAdmin::class);

        $response = $this->postJson('api/admin/support/tickets/'.$ticket->id.'/reopen')
            ->assertOk();

        $this->assertSame(['data'], array_keys($response->json()));
        $this->assertSame('En revision', $response->json('data.status'));

        $fresh = SupportTicket::query()->findOrFail($ticket->id);
        $this->assertNull($fresh->closed_at);
        $this->assertNull($fresh->closed_by);
        $this->assertSame($admin->id, (int) $fresh->updated_by);
    }

    public function test_unresolve_restores_esperando_respuesta_when_last_reply_is_admin(): void
    {
        $admin = $this->adminUser();
        $owner = User::factory()->create();
        $ticket = $this->makeTicket($owner, ['status' => 'Resuelto']);
        SupportTicketReply::query()->create([
            'ticket_id' => $ticket->id,
            'user_id' => $owner->id,
            'is_admin' => false,
            'message_markdown' => 'User message',
            'created_by' => $owner->id,
        ]);
        SupportTicketReply::query()->create([
            'ticket_id' => $ticket->id,
            'user_id' => $admin->id,
            'is_admin' => true,
            'message_markdown' => 'Admin reply',
            'created_by' => $admin->id,
        ]);

        $this->actingAs($admin, 'api');
        $this->withoutMiddleware(UserAdmin::class);

        $this->postJson('api/admin/support/tickets/'.$ticket->id.'/unresolve')
            ->assertOk()
            ->assertJsonPath('data.status', 'Esperando respuesta');
    }

    public function test_unresolve_restores_en_revision_when_last_reply_is_user(): void
    {
        $admin = $this->adminUser();
        $owner = User::factory()->create();
        $ticket = $this->makeTicket($owner, ['status' => 'Resuelto']);
        SupportTicketReply::query()->create([
            'ticket_id' => $ticket->id,
            'user_id' => $admin->id,
            'is_admin' => true,
            'message_markdown' => 'Admin reply',
            'created_by' => $admin->id,
        ]);
        SupportTicketReply::query()->create([
            'ticket_id' => $ticket->id,
            'user_id' => $owner->id,
            'is_admin' => false,
            'message_markdown' => 'User follow-up',
            'created_by' => $owner->id,
        ]);

        $this->actingAs($admin, 'api');
        $this->withoutMiddleware(UserAdmin::class);

        $this->postJson('api/admin/support/tickets/'.$ticket->id.'/unresolve')
            ->assertOk()
            ->assertJsonPath('data.status', 'En revision');
    }

    public function test_unresolve_returns_422_when_ticket_is_not_resolved(): void
    {
        $admin = $this->adminUser();
        $owner = User::factory()->create();
        $ticket = $this->makeTicket($owner, ['status' => 'Open']);

        $this->actingAs($admin, 'api');
        $this->withoutMiddleware(UserAdmin::class);

        $this->postJson('api/admin/support/tickets/'.$ticket->id.'/unresolve')
            ->assertStatus(422)
            ->assertExactJson(['error' => 'Ticket is not resolved']);
    }

    public function test_admin_reply_returns_422_when_ticket_is_resolved(): void
    {
        $admin = $this->adminUser();
        $owner = User::factory()->create();
        $ticket = $this->makeTicket($owner, ['status' => 'Resuelto']);

        $this->actingAs($admin, 'api');
        $this->withoutMiddleware(UserAdmin::class);

        $this->postJson('api/admin/support/tickets/'.$ticket->id.'/replies', [
            'message_markdown' => 'Trying after resolve',
        ])
            ->assertStatus(422)
            ->assertExactJson(['error' => 'Ticket is closed for replies']);
    }

    public function test_update_status_non_cerrado_does_not_set_closed_metadata(): void
    {
        $admin = $this->adminUser();
        $owner = User::factory()->create();
        $ticket = $this->makeTicket($owner, ['status' => 'Open']);

        $this->actingAs($admin, 'api');
        $this->withoutMiddleware(UserAdmin::class);

        $this->putJson('api/admin/support/tickets/'.$ticket->id.'/status', [
            'status' => 'Esperando respuesta',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'Esperando respuesta');

        $fresh = $ticket->fresh();
        $this->assertNull($fresh->closed_at);
        $this->assertNull($fresh->closed_by);
    }

    public function test_update_internal_note_omitted_key_clears_like_explicit_null(): void
    {
        $admin = $this->adminUser();
        $owner = User::factory()->create();
        $ticket = $this->makeTicket($owner, ['internal_note_markdown' => 'keep until cleared']);

        $this->actingAs($admin, 'api');
        $this->withoutMiddleware(UserAdmin::class);

        $this->putJson('api/admin/support/tickets/'.$ticket->id.'/internal-note', [])
            ->assertOk()
            ->assertJsonPath('data.internal_note_markdown', null);

        $this->assertNull($ticket->fresh()->internal_note_markdown);
    }

    public function test_admin_created_ticket_does_not_include_opening_auto_reply(): void
    {
        $admin = $this->adminUser();
        $owner = User::factory()->create();

        $this->actingAs($admin, 'api');
        $this->withoutMiddleware(UserAdmin::class);

        $response = $this->postJson('api/admin/support/tickets', [
            'user_id' => $owner->id,
            'type' => 'contact',
            'subject' => 'Admin opened ticket',
            'message_markdown' => 'Staff message only',
        ])->assertOk();

        $ticketId = (int) $response->json('data.id');
        $replies = SupportTicketReply::query()->where('ticket_id', $ticketId)->orderBy('id')->get();

        $this->assertCount(1, $replies);
        $this->assertTrue((bool) $replies[0]->is_admin);
        $this->assertSame('Staff message only', $replies[0]->message_markdown);
        $this->assertStringNotContainsString('¡Hola!', $replies[0]->message_markdown);
    }

    public function test_admin_can_create_account_verification_ticket_for_user_with_high_priority(): void
    {
        $admin = $this->adminUser();
        $owner = User::factory()->create();

        $this->actingAs($admin, 'api');
        $this->withoutMiddleware(UserAdmin::class);

        $response = $this->postJson('api/admin/support/tickets', [
            'user_id' => $owner->id,
            'type' => 'account_verification',
            'subject' => 'Verificacion de cuenta',
            'message_markdown' => 'Te pedimos actualizar tu documentacion.',
        ])->assertOk();

        $this->assertSame($owner->id, (int) $response->json('data.user_id'));
        $this->assertSame('account_verification', $response->json('data.type'));
        $this->assertSame('Esperando respuesta', $response->json('data.status'));
        $this->assertSame('high', $response->json('data.priority'));
        $this->assertSame(1, (int) $response->json('data.unread_for_user'));
        $this->assertSame(0, (int) $response->json('data.unread_for_admin'));

        $this->assertDatabaseHas('support_tickets', [
            'user_id' => $owner->id,
            'type' => 'account_verification',
            'subject' => 'Verificacion de cuenta',
            'status' => 'Esperando respuesta',
            'priority' => 'high',
            'created_by' => $admin->id,
        ]);

        $ticketId = (int) $response->json('data.id');
        $this->assertDatabaseHas('support_ticket_replies', [
            'ticket_id' => $ticketId,
            'user_id' => $admin->id,
            'is_admin' => true,
            'message_markdown' => 'Te pedimos actualizar tu documentacion.',
            'created_by' => $admin->id,
        ]);
    }

    public function test_resolve_with_message_creates_admin_reply(): void
    {
        $admin = $this->adminUser();
        $owner = User::factory()->create();
        $ticket = $this->makeTicket($owner, ['status' => 'Open']);
        $before = SupportTicketReply::where('ticket_id', $ticket->id)->count();

        $this->actingAs($admin, 'api');
        $this->withoutMiddleware(UserAdmin::class);

        $response = $this->postJson('api/admin/support/tickets/'.$ticket->id.'/resolve', [
            'message_markdown' => 'Fixed on our side.',
        ])
            ->assertOk();

        $this->assertSame(['data'], array_keys($response->json()));
        $this->assertSame('Resuelto', $response->json('data.status'));

        $this->assertSame($before + 1, SupportTicketReply::where('ticket_id', $ticket->id)->count());
        $this->assertDatabaseHas('support_ticket_replies', [
            'ticket_id' => $ticket->id,
            'user_id' => $admin->id,
            'is_admin' => true,
            'message_markdown' => 'Fixed on our side.',
            'created_by' => $admin->id,
        ]);
    }

    public function test_close_without_message_sets_cerrado_and_closed_metadata_without_reply(): void
    {
        $admin = $this->adminUser();
        $owner = User::factory()->create();
        $ticket = $this->makeTicket($owner, ['status' => 'Resuelto']);
        $before = SupportTicketReply::where('ticket_id', $ticket->id)->count();

        $this->actingAs($admin, 'api');
        $this->withoutMiddleware(UserAdmin::class);

        $response = $this->postJson('api/admin/support/tickets/'.$ticket->id.'/close', [])
            ->assertOk();

        $this->assertSame(['data'], array_keys($response->json()));
        $this->assertSame('Cerrado', $response->json('data.status'));

        $this->assertSame($before, SupportTicketReply::where('ticket_id', $ticket->id)->count());

        $fresh = $ticket->fresh();
        $this->assertNotNull($fresh->closed_at);
        $this->assertSame($admin->id, (int) $fresh->closed_by);
    }

    public function test_update_status_sets_closed_metadata_only_for_cerrado(): void
    {
        $admin = $this->adminUser();
        $owner = User::factory()->create();
        $ticket = $this->makeTicket($owner, ['status' => 'En revision']);

        $this->actingAs($admin, 'api');
        $this->withoutMiddleware(UserAdmin::class);

        $this->putJson('api/admin/support/tickets/'.$ticket->id.'/status', [
            'status' => 'Cerrado',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'Cerrado');

        $ticket->refresh();
        $this->assertNotNull($ticket->closed_at);
        $this->assertSame($admin->id, (int) $ticket->closed_by);
    }

    public function test_update_status_rejects_values_outside_allowed_set(): void
    {
        $admin = $this->adminUser();
        $owner = User::factory()->create();
        $ticket = $this->makeTicket($owner);

        $this->actingAs($admin, 'api');
        $this->withoutMiddleware(UserAdmin::class);

        $this->putJson('api/admin/support/tickets/'.$ticket->id.'/status', [
            'status' => 'InvalidStatus',
        ])->assertUnprocessable();
    }

    public function test_mark_needs_review_sets_status_and_creates_optional_admin_reply(): void
    {
        $admin = $this->adminUser();
        $owner = User::factory()->create();
        $ticket = $this->makeTicket($owner, ['status' => 'Open']);
        $before = SupportTicketReply::where('ticket_id', $ticket->id)->count();

        $this->actingAs($admin, 'api');
        $this->withoutMiddleware(UserAdmin::class);

        $response = $this->postJson('api/admin/support/tickets/'.$ticket->id.'/needs-review', [
            'message_markdown' => 'Seguimiento interno pendiente.',
        ])->assertOk();

        $this->assertSame('Necesita revisión', $response->json('data.status'));
        $this->assertSame($before + 1, SupportTicketReply::where('ticket_id', $ticket->id)->count());
        $this->assertDatabaseHas('support_ticket_replies', [
            'ticket_id' => $ticket->id,
            'user_id' => $admin->id,
            'is_admin' => true,
            'message_markdown' => 'Seguimiento interno pendiente.',
        ]);
    }

    public function test_mark_needs_review_without_message_only_updates_status(): void
    {
        $admin = $this->adminUser();
        $owner = User::factory()->create();
        $ticket = $this->makeTicket($owner, ['status' => 'Esperando respuesta']);
        $before = SupportTicketReply::where('ticket_id', $ticket->id)->count();

        $this->actingAs($admin, 'api');
        $this->withoutMiddleware(UserAdmin::class);

        $this->postJson('api/admin/support/tickets/'.$ticket->id.'/needs-review', [])
            ->assertOk()
            ->assertJsonPath('data.status', 'Necesita revisión');

        $this->assertSame($before, SupportTicketReply::where('ticket_id', $ticket->id)->count());
    }

    public function test_needs_review_toggle_undoes_when_already_marked_and_last_reply_is_admin(): void
    {
        $admin = $this->adminUser();
        $owner = User::factory()->create();
        $ticket = $this->makeTicket($owner, ['status' => SupportTicket::STATUS_NEEDS_REVIEW]);
        SupportTicketReply::query()->create([
            'ticket_id' => $ticket->id,
            'user_id' => $owner->id,
            'is_admin' => false,
            'message_markdown' => 'User message',
            'created_by' => $owner->id,
        ]);
        SupportTicketReply::query()->create([
            'ticket_id' => $ticket->id,
            'user_id' => $admin->id,
            'is_admin' => true,
            'message_markdown' => 'Admin reply',
            'created_by' => $admin->id,
        ]);
        $before = SupportTicketReply::where('ticket_id', $ticket->id)->count();

        $this->actingAs($admin, 'api');
        $this->withoutMiddleware(UserAdmin::class);

        $this->postJson('api/admin/support/tickets/'.$ticket->id.'/needs-review', [])
            ->assertOk()
            ->assertJsonPath('data.status', 'Esperando respuesta');

        $this->assertSame($before, SupportTicketReply::where('ticket_id', $ticket->id)->count());
    }

    public function test_needs_review_toggle_undoes_when_already_marked_and_last_reply_is_user(): void
    {
        $admin = $this->adminUser();
        $owner = User::factory()->create();
        $ticket = $this->makeTicket($owner, ['status' => SupportTicket::STATUS_NEEDS_REVIEW]);
        SupportTicketReply::query()->create([
            'ticket_id' => $ticket->id,
            'user_id' => $admin->id,
            'is_admin' => true,
            'message_markdown' => 'Admin reply',
            'created_by' => $admin->id,
        ]);
        SupportTicketReply::query()->create([
            'ticket_id' => $ticket->id,
            'user_id' => $owner->id,
            'is_admin' => false,
            'message_markdown' => 'User follow-up',
            'created_by' => $owner->id,
        ]);

        $this->actingAs($admin, 'api');
        $this->withoutMiddleware(UserAdmin::class);

        $this->postJson('api/admin/support/tickets/'.$ticket->id.'/needs-review', [])
            ->assertOk()
            ->assertJsonPath('data.status', 'En revision');
    }

    public function test_reply_with_image_stores_file_on_local_support_tickets_disk(): void
    {
        Storage::fake('local');
        $admin = $this->adminUser();
        $owner = User::factory()->create();
        $ticket = $this->makeTicket($owner);

        $this->actingAs($admin, 'api');
        $this->withoutMiddleware(UserAdmin::class);

        $this->post('api/admin/support/tickets/'.$ticket->id.'/replies', [
            'message_markdown' => 'See screenshot',
            'attachments' => [UploadedFile::fake()->image('screen.png')],
        ])->assertOk();

        $attachment = SupportTicketAttachment::query()
            ->whereIn('reply_id', SupportTicketReply::where('ticket_id', $ticket->id)->pluck('id'))
            ->first();
        $this->assertNotNull($attachment);
        $this->assertStringStartsWith('support_tickets/'.$ticket->id.'/', $attachment->path);
        Storage::disk('local')->assertExists($attachment->path);
    }

    public function test_admin_can_stream_ticket_attachment_image(): void
    {
        Storage::fake('local');
        $admin = $this->adminUser();
        $owner = User::factory()->create();
        $ticket = $this->makeTicket($owner);
        $path = 'support_tickets/'.$ticket->id.'/1/shot.png';
        Storage::disk('local')->put($path, 'png-bytes');
        $reply = SupportTicketReply::create([
            'ticket_id' => $ticket->id,
            'user_id' => $owner->id,
            'is_admin' => false,
            'message_markdown' => 'Hi',
        ]);
        $attachment = SupportTicketAttachment::create([
            'ticket_id' => null,
            'reply_id' => $reply->id,
            'user_id' => $owner->id,
            'path' => $path,
            'original_name' => 'shot.png',
            'mime' => 'image/png',
            'size_bytes' => 9,
        ]);

        $this->actingAs($admin, 'api');
        $this->withoutMiddleware(UserAdmin::class);

        $this->get('api/admin/support/tickets/'.$ticket->id.'/attachments/'.$attachment->id.'/image')
            ->assertOk()
            ->assertHeader('content-type', 'image/png');
    }

    public function test_purge_attachments_deletes_all_ticket_files_and_records(): void
    {
        Storage::fake('local');
        $admin = $this->adminUser();
        $owner = User::factory()->create();
        $ticket = $this->makeTicket($owner);
        $path = 'support_tickets/'.$ticket->id.'/1/a.png';
        Storage::disk('local')->put($path, 'x');
        $reply = SupportTicketReply::create([
            'ticket_id' => $ticket->id,
            'user_id' => $owner->id,
            'is_admin' => false,
            'message_markdown' => 'Hi',
        ]);
        SupportTicketAttachment::create([
            'ticket_id' => null,
            'reply_id' => $reply->id,
            'user_id' => $owner->id,
            'path' => $path,
            'original_name' => 'a.png',
            'mime' => 'image/png',
            'size_bytes' => 1,
        ]);

        $this->actingAs($admin, 'api');
        $this->withoutMiddleware(UserAdmin::class);

        $this->postJson('api/admin/support/tickets/'.$ticket->id.'/purge-attachments')
            ->assertOk()
            ->assertJsonPath('message', 'Attachments purged');

        $this->assertFalse(Storage::disk('local')->exists($path));
        $this->assertSame(0, SupportTicketAttachment::query()->where('reply_id', $reply->id)->count());
    }

    public function test_update_priority_persists_and_returns_ticket_payload(): void
    {
        $admin = $this->adminUser();
        $owner = User::factory()->create();
        $ticket = $this->makeTicket($owner, ['priority' => 'normal']);

        $this->actingAs($admin, 'api');
        $this->withoutMiddleware(UserAdmin::class);

        $this->putJson('api/admin/support/tickets/'.$ticket->id.'/priority', [
            'priority' => 'high',
        ])
            ->assertOk()
            ->assertJsonPath('data.priority', 'high');

        $this->assertSame('high', $ticket->fresh()->priority);
        $this->assertSame($admin->id, (int) $ticket->fresh()->updated_by);
    }

    public function test_update_priority_rejects_invalid_value(): void
    {
        $admin = $this->adminUser();
        $owner = User::factory()->create();
        $ticket = $this->makeTicket($owner);

        $this->actingAs($admin, 'api');
        $this->withoutMiddleware(UserAdmin::class);

        $this->putJson('api/admin/support/tickets/'.$ticket->id.'/priority', [
            'priority' => 'urgent',
        ])->assertUnprocessable();
    }

    public function test_update_type_persists_and_returns_ticket_payload(): void
    {
        $admin = $this->adminUser();
        $owner = User::factory()->create();
        $ticket = $this->makeTicket($owner, ['type' => 'feedback', 'priority' => 'low']);

        $this->actingAs($admin, 'api');
        $this->withoutMiddleware(UserAdmin::class);

        $this->putJson('api/admin/support/tickets/'.$ticket->id.'/type', [
            'type' => 'bug_report',
        ])
            ->assertOk()
            ->assertJsonPath('data.type', 'bug_report')
            ->assertJsonPath('data.priority', 'normal');

        $fresh = $ticket->fresh();
        $this->assertSame('bug_report', $fresh->type);
        $this->assertSame('normal', $fresh->priority);
        $this->assertSame($admin->id, (int) $fresh->updated_by);
    }

    public function test_update_type_syncs_priority_to_category_default(): void
    {
        $admin = $this->adminUser();
        $owner = User::factory()->create();
        $ticket = $this->makeTicket($owner, ['type' => 'feedback', 'priority' => 'high']);

        $this->actingAs($admin, 'api');
        $this->withoutMiddleware(UserAdmin::class);

        $this->putJson('api/admin/support/tickets/'.$ticket->id.'/type', [
            'type' => 'report',
        ])
            ->assertOk()
            ->assertJsonPath('data.type', 'report')
            ->assertJsonPath('data.priority', 'high');

        $this->assertSame('high', $ticket->fresh()->priority);
    }

    public function test_update_type_downgrades_priority_when_new_category_defaults_lower(): void
    {
        $admin = $this->adminUser();
        $owner = User::factory()->create();
        $ticket = $this->makeTicket($owner, ['type' => 'account_verification', 'priority' => 'high']);

        $this->actingAs($admin, 'api');
        $this->withoutMiddleware(UserAdmin::class);

        $this->putJson('api/admin/support/tickets/'.$ticket->id.'/type', [
            'type' => 'feedback',
        ])
            ->assertOk()
            ->assertJsonPath('data.type', 'feedback')
            ->assertJsonPath('data.priority', 'low');

        $this->assertSame('low', $ticket->fresh()->priority);
    }

    public function test_update_type_rejects_invalid_value(): void
    {
        $admin = $this->adminUser();
        $owner = User::factory()->create();
        $ticket = $this->makeTicket($owner);

        $this->actingAs($admin, 'api');
        $this->withoutMiddleware(UserAdmin::class);

        $this->putJson('api/admin/support/tickets/'.$ticket->id.'/type', [
            'type' => 'invalid_category',
        ])->assertUnprocessable();
    }

    public function test_update_internal_note_persists_text_and_can_clear(): void
    {
        $admin = $this->adminUser();
        $owner = User::factory()->create();
        $ticket = $this->makeTicket($owner, ['internal_note_markdown' => 'old']);

        $this->actingAs($admin, 'api');
        $this->withoutMiddleware(UserAdmin::class);

        $this->putJson('api/admin/support/tickets/'.$ticket->id.'/internal-note', [
            'internal_note_markdown' => 'Escalated — watch billing.',
        ])
            ->assertOk()
            ->assertJsonPath('data.internal_note_markdown', 'Escalated — watch billing.');

        $this->putJson('api/admin/support/tickets/'.$ticket->id.'/internal-note', [
            'internal_note_markdown' => null,
        ])
            ->assertOk()
            ->assertJsonPath('data.internal_note_markdown', null);

        $this->assertNull($ticket->fresh()->internal_note_markdown);
    }

    public function test_resolve_without_message_updates_status_without_extra_reply(): void
    {
        $admin = $this->adminUser();
        $owner = User::factory()->create();
        $ticket = $this->makeTicket($owner, ['status' => 'Open']);
        $replyCountBefore = SupportTicketReply::where('ticket_id', $ticket->id)->count();

        $this->actingAs($admin, 'api');
        $this->withoutMiddleware(UserAdmin::class);

        $this->postJson('api/admin/support/tickets/'.$ticket->id.'/resolve', [])
            ->assertOk()
            ->assertJsonPath('data.status', 'Resuelto');

        $this->assertSame($replyCountBefore, SupportTicketReply::where('ticket_id', $ticket->id)->count());
    }

    public function test_close_with_message_creates_admin_reply_and_closed_fields(): void
    {
        $admin = $this->adminUser();
        $owner = User::factory()->create();
        $ticket = $this->makeTicket($owner, ['status' => 'Resuelto']);

        $this->actingAs($admin, 'api');
        $this->withoutMiddleware(UserAdmin::class);

        $this->postJson('api/admin/support/tickets/'.$ticket->id.'/close', [
            'message_markdown' => 'Closing after confirmation.',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'Cerrado');

        $this->assertDatabaseHas('support_ticket_replies', [
            'ticket_id' => $ticket->id,
            'user_id' => $admin->id,
            'is_admin' => true,
            'message_markdown' => 'Closing after confirmation.',
            'created_by' => $admin->id,
        ]);

        $fresh = $ticket->fresh();
        $this->assertNotNull($fresh->closed_at);
        $this->assertSame($admin->id, (int) $fresh->closed_by);
    }

    public function test_reply_rejects_more_than_three_attachments(): void
    {
        Storage::fake('local');

        $admin = $this->adminUser();
        $owner = User::factory()->create();
        $ticket = $this->makeTicket($owner);

        $this->actingAs($admin, 'api');
        $this->withoutMiddleware(UserAdmin::class);

        $files = [
            UploadedFile::fake()->image('a.png', 10, 10),
            UploadedFile::fake()->image('b.png', 10, 10),
            UploadedFile::fake()->image('c.png', 10, 10),
            UploadedFile::fake()->image('d.png', 10, 10),
        ];

        $this->withHeaders(['Accept' => 'application/json'])
            ->post('api/admin/support/tickets/'.$ticket->id.'/replies', [
                'message_markdown' => 'Four files.',
                'attachments' => $files,
            ])
            ->assertUnprocessable();
    }

    public function test_reply_rejects_attachment_with_disallowed_mime(): void
    {
        Storage::fake('local');

        $admin = $this->adminUser();
        $owner = User::factory()->create();
        $ticket = $this->makeTicket($owner);

        $this->actingAs($admin, 'api');
        $this->withoutMiddleware(UserAdmin::class);

        $bad = UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf');

        $this->withHeaders(['Accept' => 'application/json'])
            ->post('api/admin/support/tickets/'.$ticket->id.'/replies', [
                'message_markdown' => 'See attached.',
                'attachments' => [$bad],
            ])
            ->assertUnprocessable();
    }

    public function test_reply_invokes_notification_services_with_ticket_from_and_ticket_owner(): void
    {
        $admin = $this->adminUser();
        $owner = User::factory()->create();
        $ticket = $this->makeTicket($owner);

        $this->mock(NotificationServices::class, function ($mock) use ($ticket, $admin, $owner) {
            $mock->shouldReceive('send')
                ->twice()
                ->withArgs(function ($notification, $users, $channel) use ($ticket, $admin, $owner) {
                    if (! $notification instanceof SupportTicketReplyNotification) {
                        return false;
                    }
                    $t = $notification->getAttribute('ticket');
                    $from = $notification->getAttribute('from');
                    if ($t === null || $from === null) {
                        return false;
                    }
                    if ((int) $t->id !== (int) $ticket->id || (int) $from->id !== (int) $admin->id) {
                        return false;
                    }
                    if (! $users instanceof User || (int) $users->id !== (int) $owner->id) {
                        return false;
                    }

                    return in_array($channel, [
                        \STS\Services\Notifications\Channels\DatabaseChannel::class,
                        \STS\Services\Notifications\Channels\PushChannel::class,
                    ], true);
                });
        });

        $this->actingAs($admin, 'api');
        $this->withoutMiddleware(UserAdmin::class);

        $this->postJson('api/admin/support/tickets/'.$ticket->id.'/replies', [
            'message_markdown' => 'Hello owner.',
        ])->assertOk();
    }

    public function test_assign_me_claims_assignable_ticket_for_current_admin(): void
    {
        $admin = $this->adminUser();
        $owner = User::factory()->create();
        $ticket = $this->makeTicket($owner, ['status' => 'Open', 'subject' => 'assign-me']);

        $this->actingAs($admin, 'api');
        $this->withoutMiddleware(UserAdmin::class);

        $response = $this->postJson('api/admin/support/tickets/'.$ticket->id.'/assign-me')->assertOk();
        $data = $response->json('data');

        $this->assertSame($admin->id, (int) $data['assigned_to_user_id']);
        $this->assertNotNull($data['assigned_at']);
        $this->assertSame($admin->id, (int) $data['assigned_to']['id']);
        $this->assertSame($admin->name, $data['assigned_to']['name']);
    }

    public function test_assign_me_returns_conflict_when_ticket_assigned_to_another_admin(): void
    {
        $adminA = $this->adminUser();
        $adminB = $this->adminUser();
        $owner = User::factory()->create();
        $ticket = $this->makeTicket($owner, [
            'status' => 'Open',
            'assigned_to_user_id' => $adminA->id,
            'assigned_at' => now(),
            'subject' => 'already-assigned',
        ]);

        $this->actingAs($adminB, 'api');
        $this->withoutMiddleware(UserAdmin::class);

        $this->postJson('api/admin/support/tickets/'.$ticket->id.'/assign-me')
            ->assertStatus(409);
    }

    public function test_assign_me_returns_unprocessable_when_ticket_not_assignable(): void
    {
        $admin = $this->adminUser();
        $owner = User::factory()->create();
        $ticket = $this->makeTicket($owner, [
            'status' => 'Esperando respuesta',
            'unread_for_admin' => 0,
            'subject' => 'waiting-user',
        ]);

        $this->actingAs($admin, 'api');
        $this->withoutMiddleware(UserAdmin::class);

        $this->postJson('api/admin/support/tickets/'.$ticket->id.'/assign-me')
            ->assertStatus(422);
    }

    public function test_unassign_me_clears_assignment_for_current_admin(): void
    {
        $admin = $this->adminUser();
        $owner = User::factory()->create();
        $ticket = $this->makeTicket($owner, [
            'status' => 'Open',
            'assigned_to_user_id' => $admin->id,
            'assigned_at' => now(),
            'subject' => 'unassign-me',
        ]);

        $this->actingAs($admin, 'api');
        $this->withoutMiddleware(UserAdmin::class);

        $response = $this->postJson('api/admin/support/tickets/'.$ticket->id.'/unassign-me')->assertOk();
        $data = $response->json('data');

        $this->assertNull($data['assigned_to_user_id']);
        $this->assertNull($data['assigned_at']);
        $this->assertNull($data['assigned_to']);
    }

    public function test_unassign_me_returns_forbidden_for_non_assignee(): void
    {
        $assignee = $this->adminUser();
        $otherAdmin = $this->adminUser();
        $owner = User::factory()->create();
        $ticket = $this->makeTicket($owner, [
            'status' => 'Open',
            'assigned_to_user_id' => $assignee->id,
            'assigned_at' => now(),
            'subject' => 'forbidden-unassign',
        ]);

        $this->actingAs($otherAdmin, 'api');
        $this->withoutMiddleware(UserAdmin::class);

        $this->postJson('api/admin/support/tickets/'.$ticket->id.'/unassign-me')
            ->assertStatus(403);
    }

    public function test_index_releases_expired_assignment_before_returning_rows(): void
    {
        \Carbon\Carbon::setTestNow('2026-07-19 12:00:00');
        config()->set('carpoolear.support_ticket_assignment_timeout_minutes', 10);

        $admin = $this->adminUser();
        $owner = User::factory()->create();
        $expired = $this->makeTicket($owner, [
            'status' => 'Open',
            'subject' => 'expired-assignment',
            'assigned_to_user_id' => $admin->id,
            'assigned_at' => now()->subMinutes(11),
        ]);

        $this->actingAs($admin, 'api');
        $this->withoutMiddleware(UserAdmin::class);

        $rows = collect($this->getJson('api/admin/support/tickets')->assertOk()->json('data'));
        $row = $rows->firstWhere('id', $expired->id);

        $this->assertNotNull($row);
        $this->assertNull($row['assigned_to_user_id']);
        $this->assertNull($row['assigned_to']);
    }

    public function test_index_includes_assigned_admin_for_claimed_ticket(): void
    {
        $admin = $this->adminUser();
        $owner = User::factory()->create();
        $ticket = $this->makeTicket($owner, [
            'status' => 'Open',
            'subject' => 'claimed-ticket',
            'assigned_to_user_id' => $admin->id,
            'assigned_at' => now(),
        ]);

        $this->actingAs($admin, 'api');
        $this->withoutMiddleware(UserAdmin::class);

        $rows = collect($this->getJson('api/admin/support/tickets')->assertOk()->json('data'));
        $row = $rows->firstWhere('id', $ticket->id);

        $this->assertSame($admin->id, (int) $row['assigned_to_user_id']);
        $this->assertSame($admin->name, $row['assigned_to']['name']);
    }

    public function test_admin_reply_clears_ticket_assignment(): void
    {
        $admin = $this->adminUser();
        $owner = User::factory()->create();
        $ticket = $this->makeTicket($owner, [
            'status' => 'Open',
            'subject' => 'reply-clears-assignment',
            'assigned_to_user_id' => $admin->id,
            'assigned_at' => now(),
        ]);

        $this->actingAs($admin, 'api');
        $this->withoutMiddleware(UserAdmin::class);

        $this->postJson('api/admin/support/tickets/'.$ticket->id.'/replies', [
            'message_markdown' => 'Handled now.',
        ])->assertOk();

        $fresh = $ticket->fresh();
        $this->assertNull($fresh->assigned_to_user_id);
        $this->assertNull($fresh->assigned_at);
    }

    public function test_admin_reply_returns_forbidden_when_ticket_assigned_to_another_admin(): void
    {
        $assignee = $this->adminUser();
        $otherAdmin = $this->adminUser();
        $owner = User::factory()->create();
        $ticket = $this->makeTicket($owner, [
            'status' => 'Open',
            'subject' => 'assigned-to-other',
            'assigned_to_user_id' => $assignee->id,
            'assigned_at' => now(),
        ]);

        $this->actingAs($otherAdmin, 'api');
        $this->withoutMiddleware(UserAdmin::class);

        $this->postJson('api/admin/support/tickets/'.$ticket->id.'/replies', [
            'message_markdown' => 'Should not post.',
        ])->assertStatus(403);
    }
}
