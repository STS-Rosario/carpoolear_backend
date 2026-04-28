<?php

namespace Tests\Unit\Listeners\Notification;

use Mockery;
use STS\Events\Friend\Accept as AcceptEvent;
use STS\Listeners\Notification\FriendAccept;
use STS\Models\User;
use Tests\TestCase;

class FriendAcceptTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_handle_creates_notification_sets_sender_and_notifies_recipient(): void
    {
        $from = User::factory()->create();
        $to = User::factory()->create();

        $notificationMock = Mockery::mock('overload:STS\\Notifications\\FriendAcceptNotification');
        $notificationMock->shouldReceive('setAttribute')
            ->once()
            ->with('from', $from);
        $notificationMock->shouldReceive('notify')
            ->once()
            ->with($to);

        $listener = new FriendAccept;
        $listener->handle(new AcceptEvent($from, $to));

        $this->assertTrue(true);
    }
}
