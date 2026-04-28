<?php

namespace Tests\Unit\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use STS\Models\SupportTicket;
use STS\Models\SupportTicketAttachment;
use STS\Models\SupportTicketReply;
use STS\Models\User;
use STS\Services\SupportTicketService;
use Tests\TestCase;

class SupportTicketServiceTest extends TestCase
{
    public function test_store_reply_attachments_persists_only_uploaded_files(): void
    {
        Storage::fake('public');
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

        $service = new SupportTicketService;
        $service->storeReplyAttachments(
            [$validFile, 'not-a-file'],
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
        $this->assertStringContainsString('support/', $attachment->path);
        Storage::disk('public')->assertExists($attachment->path);
    }

    public function test_apply_admin_reply_transition_updates_status_and_counters(): void
    {
        $ticket = new SupportTicket([
            'status' => 'Open',
            'unread_for_user' => 0,
            'unread_for_admin' => 3,
        ]);

        $service = new SupportTicketService;
        $service->applyAdminReplyTransition($ticket, 99);

        $this->assertSame('En revision', $ticket->status);
        $this->assertSame(1, $ticket->unread_for_user);
        $this->assertSame(0, $ticket->unread_for_admin);
        $this->assertSame(99, (int) $ticket->updated_by);
        $this->assertNotNull($ticket->last_reply_at);
    }

    public function test_apply_admin_reply_transition_keeps_non_transitionable_status(): void
    {
        $ticket = new SupportTicket([
            'status' => 'Resuelto',
            'unread_for_user' => 5,
            'unread_for_admin' => 2,
        ]);

        $service = new SupportTicketService;
        $service->applyAdminReplyTransition($ticket, 55);

        $this->assertSame('Resuelto', $ticket->status);
        $this->assertSame(6, $ticket->unread_for_user);
        $this->assertSame(0, $ticket->unread_for_admin);
        $this->assertSame(55, (int) $ticket->updated_by);
        $this->assertNotNull($ticket->last_reply_at);
    }
}
