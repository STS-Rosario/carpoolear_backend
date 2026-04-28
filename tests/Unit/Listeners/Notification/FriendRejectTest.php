<?php

namespace Tests\Unit\Listeners\Notification;

use Mockery;
use STS\Events\Friend\Reject as RejectEvent;
use STS\Listeners\Notification\FriendReject;
use STS\Models\User;
use Tests\TestCase;

class FriendRejectTest extends TestCase
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

        $notificationMock = Mockery::mock('overload:STS\\Notifications\\FriendRejectNotification');
        $notificationMock->shouldReceive('setAttribute')
            ->once()
            ->with('from', $from);
        $notificationMock->shouldReceive('notify')
            ->once()
            ->with($to);

        $listener = new FriendReject;
        $listener->handle(new RejectEvent($from, $to));

        $this->assertTrue(true);
    }
}
