<?php

namespace Tests\Unit\Notifications;

use STS\Models\Trip;
use STS\Notifications\RequestRemainderNotification;
use Tests\TestCase;

class RequestRemainderNotificationTest extends TestCase
{
    public function test_to_email_returns_expected_static_payload(): void
    {
        $notification = new RequestRemainderNotification;

        $email = $notification->toEmail(null);

        $this->assertSame(__('notifications.request_remainder.title'), $email['title']);
        $this->assertSame('request_remainder', $email['email_view']);
        $this->assertSame(config('app.url').'/app/profile/me#0', $email['url']);
    }

    public function test_to_string_and_push_message_use_request_remainder_translation(): void
    {
        $notification = new RequestRemainderNotification;
        $message = __('notifications.request_remainder.message');

        $this->assertSame($message, $notification->toString());

        $push = $notification->toPush(null, null);
        $this->assertSame($message, $push['message']);
        $this->assertSame('/my-trips', $push['url']);
        $this->assertNull($push['extras']['id']);
    }

    public function test_get_extras_and_push_include_trip_id_when_available(): void
    {
        $trip = Trip::factory()->create();
        $notification = new RequestRemainderNotification;
        $notification->setAttribute('trip', $trip);

        $extras = $notification->getExtras();
        $push = $notification->toPush(null, null);

        $this->assertSame('my-trips', $extras['type']);
        $this->assertSame($trip->id, $extras['trip_id']);
        $this->assertSame($trip->id, $push['extras']['id']);
    }
}
