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
        $this->assertSame('En revision', $response->json('data.status'));

        $this->assertDatabaseHas('support_ticket_replies', [
            'ticket_id' => $ticket->id,
            'user_id' => $admin->id,
            'is_admin' => true,
            'message_markdown' => 'We are looking into this.',
        ]);
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
        $this->assertSame('En revision', $response->json('data.status'));
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

    public function test_reply_rejects_attachment_with_disallowed_mime(): void
    {
        Storage::fake('public');

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
}
