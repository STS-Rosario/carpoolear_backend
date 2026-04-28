<?php

namespace Tests\Unit\Notifications;

use STS\Models\Trip;
use STS\Notifications\HourLeftNotification;
use Tests\TestCase;

class HourLeftNotificationTest extends TestCase
{
    public function test_to_email_and_to_string_use_trip_destination_when_present(): void
    {
        $trip = Trip::factory()->create(['to_town' => 'Rosario']);
        $notification = new HourLeftNotification;
        $notification->setAttribute('trip', $trip);

        $email = $notification->toEmail(null);
        $message = $notification->toString();

        $this->assertSame(__('notifications.hour_left.title', ['destination' => 'Rosario']), $email['title']);
        $this->assertSame('hour_left', $email['email_view']);
        $this->assertSame(config('app.url').'/app/trips/'.$trip->id, $email['url']);
        $this->assertSame(__('notifications.hour_left.message', ['destination' => 'Rosario']), $message);
    }

    public function test_to_string_and_push_fallback_to_unknown_destination_without_trip(): void
    {
        $notification = new HourLeftNotification;
        $unknown = __('notifications.destination_unknown');

        $expected = __('notifications.hour_left.message', ['destination' => $unknown]);
        $this->assertSame($expected, $notification->toString());

        $push = $notification->toPush(null, null);
        $this->assertSame($expected, $push['message']);
        $this->assertSame('/trips/', $push['url']);
        $this->assertNull($push['extras']['id']);
    }

    public function test_get_extras_and_push_include_trip_id_when_trip_exists(): void
    {
        $trip = Trip::factory()->create();
        $notification = new HourLeftNotification;
        $notification->setAttribute('trip', $trip);

        $extras = $notification->getExtras();
        $push = $notification->toPush(null, null);

        $this->assertSame('trip', $extras['type']);
        $this->assertSame($trip->id, $extras['trip_id']);
        $this->assertSame($trip->id, $push['extras']['id']);
    }
}
