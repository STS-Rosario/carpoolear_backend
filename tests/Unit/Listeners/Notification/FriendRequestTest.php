<?php

namespace Tests\Unit\Listeners\Notification;

use Mockery;
use STS\Events\Friend\Request as RequestEvent;
use STS\Listeners\Notification\FriendRequest;
use STS\Models\User;
use Tests\TestCase;

class FriendRequestTest extends TestCase
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

        $notificationMock = Mockery::mock('overload:STS\\Notifications\\FriendRequestNotification');
        $notificationMock->shouldReceive('setAttribute')
            ->once()
            ->with('from', $from);
        $notificationMock->shouldReceive('notify')
            ->once()
            ->with($to);

        $listener = new FriendRequest;
        $listener->handle(new RequestEvent($from, $to));

        $this->assertTrue(true);
    }
}
