<?php

namespace Tests\Unit\Notifications;

use STS\Models\Trip;
use STS\Notifications\LiveLocationAutoStoppedNotification;
use Tests\TestCase;

class LiveLocationAutoStoppedNotificationTest extends TestCase
{
    public function test_push_message_and_resume_url(): void
    {
        $trip = Trip::factory()->create();
        $notification = new LiveLocationAutoStoppedNotification;
        $notification->setAttribute('trip', $trip);

        $push = $notification->toPush(null, null);

        $this->assertSame(
            __('notifications.live_location_auto_stopped.message'),
            $push['message']
        );
        $this->assertSame('/trips/'.$trip->id.'/live', $push['url']);
    }
}
