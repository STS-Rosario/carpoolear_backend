<?php

namespace Tests\Unit\Notifications;

use STS\Models\Trip;
use STS\Models\User;
use STS\Notifications\RejectPassengerNotification;
use Tests\TestCase;

class RejectPassengerNotificationTest extends TestCase
{
    public function test_to_email_contains_reject_specific_fields_when_sender_and_trip_are_present(): void
    {
        $from = User::factory()->create(['name' => 'Driver Reject']);
        $trip = Trip::factory()->create(['user_id' => $from->id]);
        $notification = new RejectPassengerNotification;
        $notification->setAttribute('from', $from);
        $notification->setAttribute('trip', $trip);

        $email = $notification->toEmail(null);

        $this->assertSame(__('notifications.reject_passenger.title', ['name' => 'Driver Reject']), $email['title']);
        $this->assertSame('passenger_email', $email['email_view']);
        $this->assertSame('reject', $email['type']);
        $this->assertSame(__('notifications.reject_passenger.status'), $email['reason_message']);
        $this->assertSame(config('app.url').'/app/trips/'.$trip->id, $email['url']);
    }

    public function test_to_string_and_push_fallback_to_someone_when_sender_missing(): void
    {
        $notification = new RejectPassengerNotification;

        $expectedMessage = __('notifications.reject_passenger.message', ['name' => __('notifications.someone')]);
        $this->assertSame($expectedMessage, $notification->toString());

        $push = $notification->toPush(null, null);
        $this->assertSame($expectedMessage, $push['message']);
        $this->assertSame('/trips/', $push['url']);
        $this->assertNull($push['extras']['id']);
    }

    public function test_get_extras_and_push_include_trip_id_when_available(): void
    {
        $trip = Trip::factory()->create();
        $notification = new RejectPassengerNotification;
        $notification->setAttribute('trip', $trip);

        $extras = $notification->getExtras();
        $push = $notification->toPush(null, null);

        $this->assertSame('trip', $extras['type']);
        $this->assertSame($trip->id, $extras['trip_id']);
        $this->assertSame($trip->id, $push['extras']['id']);
    }
}
