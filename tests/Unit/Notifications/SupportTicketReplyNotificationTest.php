<?php

namespace Tests\Unit\Notifications;

use STS\Notifications\SupportTicketReplyNotification;
use Tests\TestCase;

class SupportTicketReplyNotificationTest extends TestCase
{
    public function test_to_string_returns_expected_literal_message(): void
    {
        $notification = new SupportTicketReplyNotification;

        $this->assertSame('Tenes una nueva respuesta de Carpoolear', $notification->toString());
    }

    public function test_get_extras_returns_ticket_type_and_ticket_id_when_present(): void
    {
        $notification = new SupportTicketReplyNotification;
        $notification->setAttribute('ticket', (object) ['id' => 123]);

        $extras = $notification->getExtras();

        $this->assertSame('ticket', $extras['type']);
        $this->assertSame(123, $extras['ticket_id']);
    }

    public function test_to_push_builds_ticket_url_and_null_fallback_without_ticket(): void
    {
        $notification = new SupportTicketReplyNotification;
        $withTicket = new SupportTicketReplyNotification;
        $withTicket->setAttribute('ticket', (object) ['id' => 987]);

        $pushWithTicket = $withTicket->toPush(null, null);
        $this->assertSame('Tenes una nueva respuesta de Carpoolear', $pushWithTicket['message']);
        $this->assertSame('/tickets/987', $pushWithTicket['url']);
        $this->assertSame('ticket', $pushWithTicket['type']);
        $this->assertSame(987, $pushWithTicket['extras']['id']);

        $pushWithoutTicket = $notification->toPush(null, null);
        $this->assertSame('/tickets/', $pushWithoutTicket['url']);
        $this->assertNull($pushWithoutTicket['extras']['id']);
    }
}
