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
    public function test_store_reply_attachments_continues_after_invalid_item_and_processes_next_file(): void
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

        $validFile = UploadedFile::fake()->create('proof.pdf', 15, 'application/pdf');

        $service = new SupportTicketService;
        $service->storeReplyAttachments(
            ['not-a-file', $validFile],
            $user->id,
            $reply->id
        );

        $this->assertSame(1, SupportTicketAttachment::query()->where('reply_id', $reply->id)->count());
    }

    public function test_store_reply_attachments_with_empty_array_creates_no_records(): void
    {
        Storage::fake('public');
        $service = new SupportTicketService;

        $service->storeReplyAttachments([], 1, 1);

        $this->assertSame(0, SupportTicketAttachment::query()->count());
    }

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
        $this->assertMatchesRegularExpression(
            '#^support/\d{4}/\d{2}/[A-Z0-9]{26}_[A-Za-z0-9]{20}\.png$#',
            $attachment->path
        );
        $this->assertSame('image/png', $attachment->mime);
        $this->assertSame(20480, (int) $attachment->size_bytes);
        Storage::disk('public')->assertExists($attachment->path);
    }

    public function test_store_reply_attachments_uses_fallback_mime_and_integer_size_when_mime_is_missing(): void
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

        $tempPath = tempnam(sys_get_temp_dir(), 'st-attachment-');
        file_put_contents($tempPath, 'manual attachment payload');
        $file = new class($tempPath) extends UploadedFile
        {
            public function __construct(string $path)
            {
                parent::__construct($path, 'evidence.bin', null, null, true);
            }

            public function getMimeType(): ?string
            {
                return null;
            }

            public function getSize(): int|false
            {
                return 3210;
            }
        };

        $service = new SupportTicketService;
        $service->storeReplyAttachments([$file], $user->id, $reply->id);
        @unlink($tempPath);

        $attachment = SupportTicketAttachment::query()->where('reply_id', $reply->id)->first();
        $this->assertNotNull($attachment);
        $this->assertSame('application/octet-stream', $attachment->mime);
        $this->assertSame(3210, (int) $attachment->size_bytes);
        $this->assertSame('evidence.bin', $attachment->original_name);
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

    public function test_apply_admin_reply_transition_from_waiting_status_sets_en_revision(): void
    {
        $ticket = new SupportTicket([
            'status' => 'Esperando respuesta',
            'unread_for_user' => 2,
            'unread_for_admin' => 4,
        ]);

        $service = new SupportTicketService;
        $service->applyAdminReplyTransition($ticket, 22);

        $this->assertSame('En revision', $ticket->status);
        $this->assertSame(3, $ticket->unread_for_user);
        $this->assertSame(0, $ticket->unread_for_admin);
        $this->assertSame(22, (int) $ticket->updated_by);
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

    public function test_apply_user_reply_transition_sets_waiting_status_and_admin_unread(): void
    {
        $ticket = new SupportTicket([
            'status' => 'En revision',
            'unread_for_user' => 4,
            'unread_for_admin' => 1,
        ]);

        $service = new SupportTicketService;
        $service->applyUserReplyTransition($ticket, 77);

        $this->assertSame('Esperando respuesta', $ticket->status);
        $this->assertSame(2, $ticket->unread_for_admin);
        $this->assertSame(0, $ticket->unread_for_user);
        $this->assertSame(77, (int) $ticket->updated_by);
        $this->assertNotNull($ticket->last_reply_at);
    }

    public function test_apply_user_reply_transition_from_open_still_sets_waiting_status(): void
    {
        $ticket = new SupportTicket([
            'status' => 'Open',
            'unread_for_user' => 0,
            'unread_for_admin' => 0,
        ]);

        $service = new SupportTicketService;
        $service->applyUserReplyTransition($ticket, 101);

        $this->assertSame('Esperando respuesta', $ticket->status);
        $this->assertSame(1, $ticket->unread_for_admin);
        $this->assertSame(0, $ticket->unread_for_user);
        $this->assertSame(101, (int) $ticket->updated_by);
        $this->assertNotNull($ticket->last_reply_at);
    }
}
