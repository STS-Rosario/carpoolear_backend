<?php

namespace Tests\Unit\Notifications;

use STS\Notifications\SupportTicketReplyNotification;
use STS\Services\Notifications\Channels\DatabaseChannel;
use STS\Services\Notifications\Channels\PushChannel;
use Tests\TestCase;

class SupportTicketReplyNotificationTest extends TestCase
{
    public function test_via_contains_database_and_push_channels(): void
    {
        $notification = new SupportTicketReplyNotification;

        $this->assertSame([
            DatabaseChannel::class,
            PushChannel::class,
        ], $notification->getVia());
    }

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
        $this->assertSame('https://carpoolear.com.ar/app/static/img/carpoolear_logo.png', $pushWithTicket['image']);

        $pushWithoutTicket = $notification->toPush(null, null);
        $this->assertSame('/tickets/', $pushWithoutTicket['url']);
        $this->assertNull($pushWithoutTicket['extras']['id']);
        $this->assertSame('https://carpoolear.com.ar/app/static/img/carpoolear_logo.png', $pushWithoutTicket['image']);
    }
}
