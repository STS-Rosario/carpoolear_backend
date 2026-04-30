<?php

namespace Tests\Unit\Events\Friend;

use PHPUnit\Framework\TestCase;
use STS\Events\Friend\Cancel;

class CancelEventTest extends TestCase
{
    public function test_constructor_exposes_from_and_to_payload(): void
    {
        $from = (object) ['id' => 101];
        $to = (object) ['id' => 202];

        $event = new Cancel($from, $to);

        $this->assertSame($from, $event->from);
        $this->assertSame($to, $event->to);
    }

    public function test_broadcast_on_returns_empty_channel_array(): void
    {
        $event = new Cancel(1, 2);

        $channels = $event->broadcastOn();

        $this->assertIsArray($channels);
        $this->assertSame([], $channels);
    }
}
