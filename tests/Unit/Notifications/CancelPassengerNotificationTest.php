<?php

namespace Tests\Unit\Notifications;

use STS\Models\Trip;
use STS\Models\User;
use STS\Notifications\CancelPassengerNotification;
use Tests\TestCase;

class CancelPassengerNotificationTest extends TestCase
{
    public function test_driver_cancel_message_is_used_for_email_string_and_push(): void
    {
        $from = User::factory()->create(['name' => 'Driver A']);
        $trip = Trip::factory()->create(['user_id' => $from->id]);
        $notification = new CancelPassengerNotification;
        $notification->setAttribute('from', $from);
        $notification->setAttribute('trip', $trip);
        $notification->setAttribute('is_driver', true);

        $expected = __('notifications.cancel_passenger.driver_removed', ['name' => 'Driver A']);
        $email = $notification->toEmail(null);
        $push = $notification->toPush(null, null);

        $this->assertSame($expected, $email['title']);
        $this->assertSame($expected, $notification->toString());
        $this->assertSame($expected, $push['message']);
        $this->assertSame(config('app.url').'/app/trips/'.$trip->id, $email['url']);
        $this->assertSame('/trips/'.$trip->id, $push['url']);
    }

    public function test_passenger_cancel_message_is_used_when_is_driver_is_false(): void
    {
        $from = User::factory()->create(['name' => 'Passenger B']);
        $trip = Trip::factory()->create();
        $notification = new CancelPassengerNotification;
        $notification->setAttribute('from', $from);
        $notification->setAttribute('trip', $trip);
        $notification->setAttribute('is_driver', false);

        $expected = __('notifications.cancel_passenger.passenger_left', ['name' => 'Passenger B']);
        $this->assertSame($expected, $notification->toString());
        $this->assertSame($expected, $notification->toPush(null, null)['message']);
    }

    public function test_fallbacks_apply_when_sender_and_trip_are_missing(): void
    {
        $notification = new CancelPassengerNotification;
        $notification->setAttribute('is_driver', false);

        $expected = __('notifications.cancel_passenger.passenger_left', ['name' => __('notifications.someone')]);
        $email = $notification->toEmail(null);
        $extras = $notification->getExtras();
        $push = $notification->toPush(null, null);

        $this->assertSame($expected, $email['title']);
        $this->assertSame(config('app.url').'/app/trips/', $email['url']);
        $this->assertSame('trip', $extras['type']);
        $this->assertNull($extras['trip_id']);
        $this->assertSame('/trips/', $push['url']);
        $this->assertNull($push['extras']['id']);
    }
}
