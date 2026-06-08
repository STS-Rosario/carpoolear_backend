<?php

namespace Tests\Unit\Notifications;

use STS\Models\Trip;
use STS\Notifications\LiveLocationStopReminderNotification;
use STS\Services\Notifications\Channels\DatabaseChannel;
use STS\Services\Notifications\Channels\PushChannel;
use Tests\TestCase;

class LiveLocationStopReminderNotificationTest extends TestCase
{
    public function test_push_message_and_live_page_url(): void
    {
        $trip = Trip::factory()->create();
        $notification = new LiveLocationStopReminderNotification;
        $notification->setAttribute('trip', $trip);

        $push = $notification->toPush(null, null);

        $this->assertSame(
            'Recordá detener la ubicación en tiempo real cuando desees',
            $push['message']
        );
        $this->assertSame('/trips/'.$trip->id.'/live', $push['url']);
        $this->assertSame([
            PushChannel::class,
            DatabaseChannel::class,
        ], $notification->getVia());
    }
}
