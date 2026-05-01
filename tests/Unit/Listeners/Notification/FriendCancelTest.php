<?php

namespace Tests\Unit\Listeners\Notification;

use STS\Events\Friend\Cancel as CancelEvent;
use STS\Listeners\Notification\FriendCancel;
use STS\Models\User;
use STS\Notifications\FriendCancelNotification;
use STS\Services\Notifications\NotificationServices;
use Tests\TestCase;

class FriendCancelTest extends TestCase
{
    public function test_handle_creates_notification_sets_sender_and_notifies_recipient(): void
    {
        $from = User::factory()->create();
        $to = User::factory()->create();

        $this->mock(NotificationServices::class)
            ->shouldReceive('send')
            ->times(3)
            ->withArgs(function ($notification, $users, $channel) use ($from, $to) {
                return $notification instanceof FriendCancelNotification
                    && $notification->getAttribute('from')->is($from)
                    && $users instanceof User
                    && $users->is($to)
                    && is_string($channel);
            });

        $listener = new FriendCancel;
        $listener->handle(new CancelEvent($from, $to));
    }
}
