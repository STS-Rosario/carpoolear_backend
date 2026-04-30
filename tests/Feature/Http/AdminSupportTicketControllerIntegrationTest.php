<?php

namespace Tests\Feature\Http;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use STS\Http\Middleware\UserAdmin;
use STS\Models\SupportTicket;
use STS\Models\SupportTicketAttachment;
use STS\Models\SupportTicketReply;
use STS\Models\User;
use Tests\TestCase;

class AdminSupportTicketControllerIntegrationTest extends TestCase
{
    use DatabaseTransactions;

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

    public function test_index_returns_data_ordered_newest_first(): void
    {
        $admin = $this->adminUser();
        $owner = User::factory()->create();
        $older = $this->makeTicket($owner, ['subject' => 'older']);
        $newer = $this->makeTicket($owner, ['subject' => 'newer']);

        $this->actingAs($admin, 'api');
        $this->withoutMiddleware(UserAdmin::class);

        $rows = $this->getJson('api/admin/support/tickets')
            ->assertOk()
            ->json('data');

        $this->assertIsArray($rows);
        $this->assertGreaterThanOrEqual(2, count($rows));
        $ids = array_column($rows, 'id');
        $this->assertSame($newer->id, $ids[0], 'Admin ticket list should be newest-first');
        $this->assertContains($older->id, $ids);
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

        $payload = $this->getJson('api/admin/support/tickets/'.$ticket->id)
            ->assertOk()
            ->json('data');

        $this->assertSame($owner->id, $payload['user']['id']);
        $this->assertSame($owner->email, $payload['user']['email']);

        $ticketAttachmentNames = collect($payload['attachments'])->pluck('original_name')->all();
        $this->assertContains('ticket.png', $ticketAttachmentNames);

        $replyWithFile = collect($payload['replies'])->firstWhere('id', $userReply->id);
        $this->assertNotNull($replyWithFile);
        $this->assertSame('reply.jpg', $replyWithFile['attachments'][0]['original_name']);
    }

    public function test_reply_without_message_is_unprocessable(): void
    {
        Storage::fake('public');

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
        Storage::fake('public');

        $admin = $this->adminUser();
        $owner = User::factory()->create();
        $ticket = $this->makeTicket($owner);

        $this->actingAs($admin, 'api');
        $this->withoutMiddleware(UserAdmin::class);

        $file = UploadedFile::fake()->image('note.png', 30, 30);

        $this->post('api/admin/support/tickets/'.$ticket->id.'/replies', [
            'message_markdown' => 'Please see screenshot.',
            'attachments' => [$file],
        ])->assertOk();

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
        ]);

        $fresh = $ticket->fresh();
        $this->assertNotNull($fresh->closed_at);
        $this->assertSame($admin->id, (int) $fresh->closed_by);
    }
}
