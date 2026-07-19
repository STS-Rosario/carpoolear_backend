<?php

namespace Tests\Unit\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use STS\Models\SupportTicket;
use STS\Models\SupportTicketAttachment;
use STS\Models\SupportTicketReply;
use STS\Models\User;
use STS\Services\SupportTicketService;
use STS\Support\SupportTicketOpeningAutoReply;
use Tests\TestCase;

class SupportTicketServiceTest extends TestCase
{
    private function service(): SupportTicketService
    {
        return $this->app->make(SupportTicketService::class);
    }

    public function test_append_opening_auto_reply_creates_admin_reply_without_waiting_on_user_status(): void
    {
        $owner = User::factory()->create();
        $admin = User::factory()->create(['is_admin' => 1]);
        config()->set('carpoolear.support_ticket_auto_reply_user_id', $admin->id);

        $ticket = SupportTicket::query()->create([
            'user_id' => $owner->id,
            'type' => 'contact',
            'subject' => 'Help',
            'status' => 'Open',
            'priority' => 'normal',
            'unread_for_admin' => 1,
            'unread_for_user' => 0,
        ]);
        SupportTicketReply::query()->create([
            'ticket_id' => $ticket->id,
            'user_id' => $owner->id,
            'is_admin' => false,
            'message_markdown' => 'User question',
            'created_by' => $owner->id,
        ]);

        $service = $this->service();
        $this->assertTrue($service->appendOpeningAutoReply($ticket->fresh()));
        $this->assertFalse($service->appendOpeningAutoReply($ticket->fresh()));

        $replies = SupportTicketReply::query()->where('ticket_id', $ticket->id)->orderBy('id')->get();
        $this->assertCount(2, $replies);
        $this->assertTrue((bool) $replies[1]->is_admin);
        $this->assertSame(SupportTicketOpeningAutoReply::MARKDOWN, $replies[1]->message_markdown);
        $this->assertSame($admin->id, (int) $replies[1]->user_id);
        $fresh = $ticket->fresh();
        $this->assertSame('Open', $fresh->status);
        $this->assertSame(1, (int) $fresh->unread_for_admin);
        $this->assertSame(1, (int) $fresh->unread_for_user);
    }

    public function test_append_opening_auto_reply_preserves_status_when_ticket_already_en_revision(): void
    {
        $owner = User::factory()->create();
        $admin = User::factory()->create(['is_admin' => 1]);
        config()->set('carpoolear.support_ticket_auto_reply_user_id', $admin->id);

        $ticket = SupportTicket::query()->create([
            'user_id' => $owner->id,
            'type' => 'contact',
            'subject' => 'Help',
            'status' => 'En revision',
            'priority' => 'normal',
            'unread_for_admin' => 2,
            'unread_for_user' => 0,
        ]);
        SupportTicketReply::query()->create([
            'ticket_id' => $ticket->id,
            'user_id' => $owner->id,
            'is_admin' => false,
            'message_markdown' => 'User question',
            'created_by' => $owner->id,
        ]);

        $this->assertTrue($this->service()->appendOpeningAutoReply($ticket->fresh()));

        $fresh = $ticket->fresh();
        $this->assertSame('En revision', $fresh->status);
        $this->assertSame(2, (int) $fresh->unread_for_admin);
        $this->assertSame(1, (int) $fresh->unread_for_user);
    }

    public function test_ticket_already_has_reply_with_message_markdown_detects_existing_body(): void
    {
        $user = User::factory()->create();
        $ticket = SupportTicket::query()->create([
            'user_id' => $user->id,
            'type' => 'feedback',
            'subject' => 'Help',
            'status' => 'Open',
            'priority' => 'normal',
        ]);
        SupportTicketReply::query()->create([
            'ticket_id' => $ticket->id,
            'user_id' => $user->id,
            'is_admin' => false,
            'message_markdown' => 'Already posted',
            'created_by' => $user->id,
        ]);

        $service = $this->service();

        $this->assertTrue($service->ticketAlreadyHasReplyWithMessageMarkdown($ticket->id, 'Already posted'));
        $this->assertFalse($service->ticketAlreadyHasReplyWithMessageMarkdown($ticket->id, 'New content'));
    }

    public function test_store_reply_attachments_skips_non_files_and_stores_valid_images(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        $ticket = SupportTicket::query()->create([
            'user_id' => $user->id,
            'type' => 'bug',
            'subject' => 'App issue',
            'status' => 'Open',
            'priority' => 'normal',
        ]);
        $reply = SupportTicketReply::query()->create([
            'ticket_id' => $ticket->id,
            'user_id' => $user->id,
            'is_admin' => false,
            'message_markdown' => 'details',
        ]);

        $validFile = UploadedFile::fake()->image('proof.png');

        $this->service()->storeReplyAttachments(
            ['not-a-file', $validFile],
            $ticket->id,
            $user->id,
            $reply->id
        );

        $this->assertSame(1, SupportTicketAttachment::query()->where('reply_id', $reply->id)->count());
    }

    public function test_store_reply_attachments_with_empty_array_creates_no_records(): void
    {
        Storage::fake('local');
        $this->service()->storeReplyAttachments([], 1, 1, 1);

        $this->assertSame(0, SupportTicketAttachment::query()->count());
    }

    public function test_store_reply_attachments_persists_only_uploaded_files(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        $ticket = SupportTicket::query()->create([
            'user_id' => $user->id,
            'type' => 'bug',
            'subject' => 'App issue',
            'status' => 'Open',
            'priority' => 'normal',
        ]);
        $reply = SupportTicketReply::query()->create([
            'ticket_id' => $ticket->id,
            'user_id' => $user->id,
            'is_admin' => false,
            'message_markdown' => 'details',
        ]);

        $validFile = UploadedFile::fake()->create('evidence.png', 20, 'image/png');

        $this->service()->storeReplyAttachments(
            [$validFile, 'not-a-file'],
            $ticket->id,
            $user->id,
            $reply->id
        );

        $attachments = SupportTicketAttachment::query()->where('reply_id', $reply->id)->get();
        $this->assertCount(1, $attachments);
        $attachment = $attachments->first();
        $this->assertNotNull($attachment);
        $this->assertSame($user->id, (int) $attachment->user_id);
        $this->assertNull($attachment->ticket_id);
        $this->assertSame('evidence.png', $attachment->original_name);
        $this->assertStringStartsWith('support_tickets/'.$ticket->id.'/'.$reply->id.'/', $attachment->path);
        $this->assertSame('image/png', $attachment->mime);
        Storage::disk('local')->assertExists($attachment->path);
    }

    public function test_apply_admin_reply_transition_updates_status_and_counters(): void
    {
        $ticket = new SupportTicket([
            'status' => 'Open',
            'unread_for_user' => 0,
            'unread_for_admin' => 3,
        ]);

        $this->service()->applyAdminReplyTransition($ticket, 99);

        $this->assertSame('Esperando respuesta', $ticket->status);
        $this->assertSame(1, $ticket->unread_for_user);
        $this->assertSame(0, $ticket->unread_for_admin);
        $this->assertSame(99, (int) $ticket->updated_by);
        $this->assertNotNull($ticket->last_reply_at);
    }

    public function test_apply_admin_reply_transition_when_waiting_for_user_stays_esperando_respuesta(): void
    {
        $ticket = new SupportTicket([
            'status' => 'Esperando respuesta',
            'unread_for_user' => 2,
            'unread_for_admin' => 4,
        ]);

        $service = $this->service();
        $service->applyAdminReplyTransition($ticket, 22);

        $this->assertSame('Esperando respuesta', $ticket->status);
        $this->assertSame(3, $ticket->unread_for_user);
        $this->assertSame(0, $ticket->unread_for_admin);
        $this->assertSame(22, (int) $ticket->updated_by);
        $this->assertNotNull($ticket->last_reply_at);
    }

    public function test_apply_admin_reply_transition_keeps_necesita_revision_status(): void
    {
        $ticket = new SupportTicket([
            'status' => 'Necesita revisión',
            'unread_for_user' => 1,
            'unread_for_admin' => 2,
        ]);

        $service = $this->service();
        $service->applyAdminReplyTransition($ticket, 44);

        $this->assertSame('Necesita revisión', $ticket->status);
        $this->assertSame(2, $ticket->unread_for_user);
        $this->assertSame(0, $ticket->unread_for_admin);
    }

    public function test_apply_admin_reply_transition_keeps_non_transitionable_status(): void
    {
        $ticket = new SupportTicket([
            'status' => 'Resuelto',
            'unread_for_user' => 5,
            'unread_for_admin' => 2,
        ]);

        $service = $this->service();
        $service->applyAdminReplyTransition($ticket, 55);

        $this->assertSame('Resuelto', $ticket->status);
        $this->assertSame(6, $ticket->unread_for_user);
        $this->assertSame(0, $ticket->unread_for_admin);
        $this->assertSame(55, (int) $ticket->updated_by);
        $this->assertNotNull($ticket->last_reply_at);
    }

    public function test_apply_user_reply_transition_sets_en_revision_and_admin_unread(): void
    {
        $ticket = new SupportTicket([
            'status' => 'En revision',
            'unread_for_user' => 4,
            'unread_for_admin' => 1,
        ]);

        $this->service()->applyUserReplyTransition($ticket, 77);

        $this->assertSame('En revision', $ticket->status);
        $this->assertSame(2, $ticket->unread_for_admin);
        $this->assertSame(0, $ticket->unread_for_user);
        $this->assertSame(77, (int) $ticket->updated_by);
        $this->assertNotNull($ticket->last_reply_at);
    }

    public function test_apply_user_reply_transition_from_open_sets_en_revision(): void
    {
        $ticket = new SupportTicket([
            'status' => 'Open',
            'unread_for_user' => 0,
            'unread_for_admin' => 0,
        ]);

        $service = $this->service();
        $service->applyUserReplyTransition($ticket, 101);

        $this->assertSame('En revision', $ticket->status);
        $this->assertSame(1, $ticket->unread_for_admin);
        $this->assertSame(0, $ticket->unread_for_user);
        $this->assertSame(101, (int) $ticket->updated_by);
        $this->assertNotNull($ticket->last_reply_at);
    }

    public function test_status_after_undo_resolve_when_last_reply_is_admin(): void
    {
        $owner = User::factory()->create();
        $admin = User::factory()->create(['is_admin' => 1]);
        $ticket = SupportTicket::query()->create([
            'user_id' => $owner->id,
            'type' => 'contact',
            'subject' => 'Help',
            'status' => 'Resuelto',
            'priority' => 'normal',
        ]);
        SupportTicketReply::query()->create([
            'ticket_id' => $ticket->id,
            'user_id' => $owner->id,
            'is_admin' => false,
            'message_markdown' => 'User',
        ]);
        SupportTicketReply::query()->create([
            'ticket_id' => $ticket->id,
            'user_id' => $admin->id,
            'is_admin' => true,
            'message_markdown' => 'Admin',
        ]);

        $this->assertSame(
            'Esperando respuesta',
            $this->service()->statusAfterUndoResolve($ticket->fresh())
        );
    }

    public function test_status_after_undo_resolve_when_last_reply_is_user(): void
    {
        $owner = User::factory()->create();
        $admin = User::factory()->create(['is_admin' => 1]);
        $ticket = SupportTicket::query()->create([
            'user_id' => $owner->id,
            'type' => 'contact',
            'subject' => 'Help',
            'status' => 'Resuelto',
            'priority' => 'normal',
        ]);
        SupportTicketReply::query()->create([
            'ticket_id' => $ticket->id,
            'user_id' => $admin->id,
            'is_admin' => true,
            'message_markdown' => 'Admin',
        ]);
        SupportTicketReply::query()->create([
            'ticket_id' => $ticket->id,
            'user_id' => $owner->id,
            'is_admin' => false,
            'message_markdown' => 'User follow-up',
        ]);

        $this->assertSame(
            'En revision',
            $this->service()->statusAfterUndoResolve($ticket->fresh())
        );
    }

    public function test_unresolve_ticket_restores_status_from_last_reply(): void
    {
        $owner = User::factory()->create();
        $admin = User::factory()->create(['is_admin' => 1]);
        $ticket = SupportTicket::query()->create([
            'user_id' => $owner->id,
            'type' => 'contact',
            'subject' => 'Help',
            'status' => 'Resuelto',
            'priority' => 'normal',
        ]);
        SupportTicketReply::query()->create([
            'ticket_id' => $ticket->id,
            'user_id' => $admin->id,
            'is_admin' => true,
            'message_markdown' => 'Done',
        ]);

        $this->service()->unresolveTicket($ticket, $admin->id);

        $this->assertSame('Esperando respuesta', $ticket->fresh()->status);
        $this->assertSame($admin->id, (int) $ticket->fresh()->updated_by);
    }

    public function test_undo_needs_review_restores_esperando_respuesta_when_last_reply_is_admin(): void
    {
        $owner = User::factory()->create();
        $admin = User::factory()->create(['is_admin' => 1]);
        $ticket = SupportTicket::query()->create([
            'user_id' => $owner->id,
            'type' => 'contact',
            'subject' => 'Help',
            'status' => SupportTicket::STATUS_NEEDS_REVIEW,
            'priority' => 'normal',
        ]);
        SupportTicketReply::query()->create([
            'ticket_id' => $ticket->id,
            'user_id' => $owner->id,
            'is_admin' => false,
            'message_markdown' => 'User message',
        ]);
        SupportTicketReply::query()->create([
            'ticket_id' => $ticket->id,
            'user_id' => $admin->id,
            'is_admin' => true,
            'message_markdown' => 'Admin reply',
        ]);

        $this->service()->undoNeedsReviewTicket($ticket, $admin->id);

        $this->assertSame('Esperando respuesta', $ticket->fresh()->status);
        $this->assertSame($admin->id, (int) $ticket->fresh()->updated_by);
    }

    public function test_undo_needs_review_restores_en_revision_when_last_reply_is_user(): void
    {
        $owner = User::factory()->create();
        $admin = User::factory()->create(['is_admin' => 1]);
        $ticket = SupportTicket::query()->create([
            'user_id' => $owner->id,
            'type' => 'contact',
            'subject' => 'Help',
            'status' => SupportTicket::STATUS_NEEDS_REVIEW,
            'priority' => 'normal',
        ]);
        SupportTicketReply::query()->create([
            'ticket_id' => $ticket->id,
            'user_id' => $admin->id,
            'is_admin' => true,
            'message_markdown' => 'Admin reply',
        ]);
        SupportTicketReply::query()->create([
            'ticket_id' => $ticket->id,
            'user_id' => $owner->id,
            'is_admin' => false,
            'message_markdown' => 'User follow-up',
        ]);

        $this->service()->undoNeedsReviewTicket($ticket, $admin->id);

        $this->assertSame('En revision', $ticket->fresh()->status);
    }

    public function test_ticket_accepts_replies_is_false_for_resolved_and_closed(): void
    {
        $service = $this->service();

        $this->assertFalse($service->ticketAcceptsReplies(new SupportTicket(['status' => 'Resuelto'])));
        $this->assertFalse($service->ticketAcceptsReplies(new SupportTicket(['status' => 'Cerrado'])));
        $this->assertTrue($service->ticketAcceptsReplies(new SupportTicket(['status' => 'Open'])));
    }

    public function test_ticket_is_assignable_by_admin_for_actionable_statuses(): void
    {
        $service = $this->service();

        $this->assertTrue($service->ticketIsAssignableByAdmin(new SupportTicket(['status' => 'Open', 'unread_for_admin' => 0])));
        $this->assertTrue($service->ticketIsAssignableByAdmin(new SupportTicket(['status' => 'En revision', 'unread_for_admin' => 0])));
        $this->assertTrue($service->ticketIsAssignableByAdmin(new SupportTicket([
            'status' => SupportTicket::STATUS_NEEDS_REVIEW,
            'unread_for_admin' => 0,
        ])));
        $this->assertTrue($service->ticketIsAssignableByAdmin(new SupportTicket([
            'status' => 'Esperando respuesta',
            'unread_for_admin' => 1,
        ])));
    }

    public function test_ticket_is_not_assignable_by_admin_when_waiting_on_user_without_unread(): void
    {
        $service = $this->service();

        $this->assertFalse($service->ticketIsAssignableByAdmin(new SupportTicket([
            'status' => 'Esperando respuesta',
            'unread_for_admin' => 0,
        ])));
        $this->assertFalse($service->ticketIsAssignableByAdmin(new SupportTicket(['status' => 'Resuelto', 'unread_for_admin' => 0])));
        $this->assertFalse($service->ticketIsAssignableByAdmin(new SupportTicket(['status' => 'Cerrado', 'unread_for_admin' => 0])));
    }
}
