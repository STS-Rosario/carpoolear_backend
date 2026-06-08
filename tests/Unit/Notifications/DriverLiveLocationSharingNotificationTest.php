<?php

namespace Tests\Unit\Notifications;

use STS\Models\Trip;
use STS\Models\User;
use STS\Notifications\DriverLiveLocationSharingNotification;
use STS\Services\Notifications\Channels\DatabaseChannel;
use STS\Services\Notifications\Channels\MailChannel;
use STS\Services\Notifications\Channels\PushChannel;
use Tests\TestCase;

class DriverLiveLocationSharingNotificationTest extends TestCase
{
    public function test_via_contains_database_mail_and_push_channels(): void
    {
        $notification = new DriverLiveLocationSharingNotification;

        $this->assertSame([
            DatabaseChannel::class,
            MailChannel::class,
            PushChannel::class,
        ], $notification->getVia());
    }

    public function test_push_includes_trip_ubicacion_url_and_driver_name(): void
    {
        $driver = User::factory()->create(['name' => 'María']);
        $trip = Trip::factory()->create([
            'user_id' => $driver->id,
            'to_town' => 'Córdoba',
        ]);
        $notification = new DriverLiveLocationSharingNotification;
        $notification->setAttribute('trip', $trip);
        $notification->setAttribute('from', $driver);

        $push = $notification->toPush(null, null);
        $message = __('notifications.driver_live_location.message', [
            'name' => 'María',
            'destination' => 'Córdoba',
        ]);

        $this->assertSame($message, $push['message']);
        $this->assertSame('/trips/'.$trip->id.'/ubicacion', $push['url']);
        $this->assertSame('live_location', $notification->getExtras()['type']);
        $this->assertSame($trip->id, $notification->getExtras()['trip_id']);
    }
}
