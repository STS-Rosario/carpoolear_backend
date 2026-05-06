<?php

namespace Tests\Unit\Listeners\Notification;

use STS\Events\Rating\PendingRate as PendingRateEvent;
use STS\Listeners\Notification\PendingRate;
use STS\Models\Trip;
use STS\Models\User;
use STS\Notifications\PendingRateNotification;
use STS\Services\Notifications\NotificationServices;
use Tests\TestCase;

class PendingRateListenerTest extends TestCase
{
    public function test_handle_builds_pending_rate_notification_and_notifies_recipient_on_all_channels(): void
    {
        $recipient = User::factory()->create();
        $trip = Trip::factory()->create();
        $hash = 'pending-rate-hash';

        $this->mock(NotificationServices::class)
            ->shouldReceive('send')
            ->times(3)
            ->withArgs(function ($notification, $users, $channel) use ($recipient, $trip, $hash) {
                if (! $notification instanceof PendingRateNotification) {
                    return false;
                }

                return $notification->getAttribute('trip')->is($trip)
                    && $notification->getAttribute('hash') === $hash
                    && $users instanceof User
                    && $users->is($recipient)
                    && is_string($channel);
            });

        $listener = new PendingRate;
        $listener->handle(new PendingRateEvent($recipient, $trip, $hash));
    }
}
