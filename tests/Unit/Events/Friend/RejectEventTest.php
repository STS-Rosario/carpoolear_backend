<?php

namespace Tests\Unit\Events\Friend;

use PHPUnit\Framework\TestCase;
use STS\Events\Friend\Reject;

class RejectEventTest extends TestCase
{
    public function test_constructor_exposes_from_and_to_payload(): void
    {
        $from = (object) ['id' => 501];
        $to = (object) ['id' => 602];

        $event = new Reject($from, $to);

        $this->assertSame($from, $event->from);
        $this->assertSame($to, $event->to);
    }

    public function test_broadcast_on_returns_empty_channel_array(): void
    {
        $event = new Reject(1, 2);

        $channels = $event->broadcastOn();

        $this->assertIsArray($channels);
        $this->assertSame([], $channels);
    }
}
