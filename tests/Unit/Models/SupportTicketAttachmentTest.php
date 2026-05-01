<?php

namespace Tests\Unit\Models;

use STS\Models\SupportTicket;
use STS\Models\SupportTicketAttachment;
use STS\Models\SupportTicketReply;
use STS\Models\User;
use Tests\TestCase;

class SupportTicketAttachmentTest extends TestCase
{
    private function makeTicket(User $user): SupportTicket
    {
        return SupportTicket::query()->create([
            'user_id' => $user->id,
            'type' => 'general',
            'subject' => 'Attachment ticket',
            'status' => 'Open',
            'priority' => 'normal',
            'unread_for_user' => 0,
            'unread_for_admin' => 0,
        ]);
    }

    public function test_ticket_parent_resolves_ticket_and_not_reply(): void
    {
        $user = User::factory()->create();
        $ticket = $this->makeTicket($user);

        $attachment = SupportTicketAttachment::query()->create([
            'ticket_id' => $ticket->id,
            'reply_id' => null,
            'user_id' => $user->id,
            'path' => 'tickets/99/a.png',
            'original_name' => 'a.png',
            'mime' => 'image/png',
            'size_bytes' => 100,
        ]);

        $attachment = $attachment->fresh();
        $this->assertTrue($attachment->ticket->is($ticket));
        $this->assertNull($attachment->reply_id);
        $this->assertNull($attachment->reply);
    }

    public function test_reply_parent_resolves_reply_and_not_ticket(): void
    {
        $user = User::factory()->create();
        $ticket = $this->makeTicket($user);
        $reply = SupportTicketReply::query()->create([
            'ticket_id' => $ticket->id,
            'user_id' => $user->id,
            'is_admin' => false,
            'message_markdown' => 'See attachment',
        ]);

        $attachment = SupportTicketAttachment::query()->create([
            'ticket_id' => null,
            'reply_id' => $reply->id,
            'user_id' => $user->id,
            'path' => 'tickets/replies/7/b.pdf',
            'original_name' => 'b.pdf',
            'mime' => 'application/pdf',
            'size_bytes' => 200,
        ]);

        $attachment = $attachment->fresh();
        $this->assertTrue($attachment->reply->is($reply));
        $this->assertNull($attachment->ticket_id);
        $this->assertNull($attachment->ticket);
    }
}
