<?php

namespace Tests\Unit\Models;

use STS\Models\SupportTicket;
use STS\Models\SupportTicketAttachment;
use STS\Models\SupportTicketReply;
use STS\Models\User;
use Tests\TestCase;

class SupportTicketReplyTest extends TestCase
{
    private function makeTicket(User $user): SupportTicket
    {
        return SupportTicket::query()->create([
            'user_id' => $user->id,
            'type' => 'general',
            'subject' => 'Subject',
            'status' => 'Open',
            'priority' => 'normal',
            'unread_for_user' => 0,
            'unread_for_admin' => 0,
        ]);
    }

    public function test_belongs_to_ticket_and_user(): void
    {
        $user = User::factory()->create();
        $ticket = $this->makeTicket($user);
        $reply = SupportTicketReply::query()->create([
            'ticket_id' => $ticket->id,
            'user_id' => $user->id,
            'is_admin' => false,
            'message_markdown' => 'Reply body',
        ]);

        $reply = $reply->fresh();
        $this->assertTrue($reply->ticket->is($ticket));
        $this->assertTrue($reply->user->is($user));
    }

    public function test_is_admin_casts_to_boolean(): void
    {
        $user = User::factory()->create();
        $ticket = $this->makeTicket($user);

        $reply = SupportTicketReply::query()->create([
            'ticket_id' => $ticket->id,
            'user_id' => $user->id,
            'is_admin' => 1,
            'message_markdown' => 'Staff note',
        ]);

        $this->assertTrue($reply->fresh()->is_admin);
    }

    public function test_attachments_relation_uses_reply_as_parent(): void
    {
        $user = User::factory()->create();
        $ticket = $this->makeTicket($user);
        $reply = SupportTicketReply::query()->create([
            'ticket_id' => $ticket->id,
            'user_id' => $user->id,
            'is_admin' => false,
            'message_markdown' => 'With file',
        ]);

        SupportTicketAttachment::query()->create([
            'ticket_id' => null,
            'reply_id' => $reply->id,
            'user_id' => $user->id,
            'path' => 'tickets/replies/1/doc.pdf',
            'original_name' => 'doc.pdf',
            'mime' => 'application/pdf',
            'size_bytes' => 2048,
        ]);

        $reply = $reply->fresh();
        $this->assertSame(1, $reply->attachments()->count());
        $this->assertSame('doc.pdf', $reply->attachments()->first()->original_name);
    }
}
