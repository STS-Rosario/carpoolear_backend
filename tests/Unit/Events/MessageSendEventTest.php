<?php

namespace Tests\Unit\Events;

use PHPUnit\Framework\TestCase;
use STS\Events\MessageSend;

class MessageSendEventTest extends TestCase
{
    public function test_constructor_exposes_from_to_and_message_payload(): void
    {
        $from = (object) ['id' => 11];
        $to = (object) ['id' => 22];
        $message = (object) ['id' => 99, 'text' => 'hi'];

        $event = new MessageSend($from, $to, $message);

        $this->assertSame($from, $event->from);
        $this->assertSame($to, $event->to);
        $this->assertSame($message, $event->message);
    }

    public function test_broadcast_on_returns_empty_channel_array(): void
    {
        $event = new MessageSend(1, 2, 'plain');

        $channels = $event->broadcastOn();

        $this->assertIsArray($channels);
        $this->assertSame([], $channels);
    }
}
