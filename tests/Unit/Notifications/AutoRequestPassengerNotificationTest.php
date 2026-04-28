<?php

namespace Tests\Unit\Notifications;

use STS\Models\Trip;
use STS\Models\User;
use STS\Notifications\AutoRequestPassengerNotification;
use Tests\TestCase;

class AutoRequestPassengerNotificationTest extends TestCase
{
    public function test_to_email_uses_sender_and_trip_when_present(): void
    {
        $from = User::factory()->create(['name' => 'Auto Request User']);
        $trip = Trip::factory()->create(['user_id' => $from->id]);
        $notification = new AutoRequestPassengerNotification;
        $notification->setAttribute('from', $from);
        $notification->setAttribute('trip', $trip);

        $email = $notification->toEmail(null);

        $this->assertSame(__('notifications.auto_request_passenger.title', ['name' => 'Auto Request User']), $email['title']);
        $this->assertSame('auto_request_passenger', $email['email_view']);
        $this->assertSame(config('app.url').'/app/trips/'.$trip->id, $email['url']);
    }

    public function test_to_string_and_push_fallback_to_someone_when_sender_is_missing(): void
    {
        $notification = new AutoRequestPassengerNotification;

        $expected = __('notifications.auto_request_passenger.message', ['name' => __('notifications.someone')]);
        $this->assertSame($expected, $notification->toString());

        $push = $notification->toPush(null, null);
        $this->assertSame($expected, $push['message']);
        $this->assertSame('/trips/', $push['url']);
        $this->assertNull($push['extras']['id']);
    }

    public function test_get_extras_and_push_include_trip_id_when_trip_exists(): void
    {
        $trip = Trip::factory()->create();
        $notification = new AutoRequestPassengerNotification;
        $notification->setAttribute('trip', $trip);

        $extras = $notification->getExtras();
        $push = $notification->toPush(null, null);

        $this->assertSame('trip', $extras['type']);
        $this->assertSame($trip->id, $extras['trip_id']);
        $this->assertSame($trip->id, $push['extras']['id']);
    }
}
