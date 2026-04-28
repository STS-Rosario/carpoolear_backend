<?php

namespace Tests\Unit\Notifications;

use STS\Models\Trip;
use STS\Notifications\SubscriptionMatchNotification;
use Tests\TestCase;

class SubscriptionMatchNotificationTest extends TestCase
{
    public function test_to_email_uses_trip_url_when_trip_is_present(): void
    {
        $trip = Trip::factory()->create();
        $notification = new SubscriptionMatchNotification;
        $notification->setAttribute('trip', $trip);

        $email = $notification->toEmail(null);

        $this->assertSame(__('notifications.subscription_match.title'), $email['title']);
        $this->assertSame('subscription_match', $email['email_view']);
        $this->assertSame(config('app.url').'/app/trips/'.$trip->id, $email['url']);
    }

    public function test_to_string_and_push_use_subscription_match_message(): void
    {
        $notification = new SubscriptionMatchNotification;
        $expected = __('notifications.subscription_match.message');

        $this->assertSame($expected, $notification->toString());

        $push = $notification->toPush(null, null);
        $this->assertSame($expected, $push['message']);
        $this->assertSame('/trips/', $push['url']);
        $this->assertNull($push['extras']['id']);
    }

    public function test_get_extras_and_push_include_expected_payload(): void
    {
        $trip = Trip::factory()->create();
        $notification = new SubscriptionMatchNotification;
        $notification->setAttribute('trip', $trip);

        $extras = $notification->getExtras();
        $push = $notification->toPush(null, null);

        $this->assertSame('subscription', $extras['type']);
        $this->assertSame($trip->id, $push['extras']['id']);
    }
}
