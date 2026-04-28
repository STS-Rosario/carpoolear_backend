<?php

namespace Tests\Unit\Notifications;

use STS\Models\Trip;
use STS\Notifications\PendingRateNotification;
use Tests\TestCase;

class PendingRateNotificationTest extends TestCase
{
    public function test_to_email_uses_trip_destination_when_present(): void
    {
        $trip = Trip::factory()->create(['to_town' => 'La Plata']);
        $notification = new PendingRateNotification;
        $notification->setAttribute('trip', $trip);

        $email = $notification->toEmail(null);

        $this->assertSame(__('notifications.pending_rate.title', ['destination' => 'La Plata']), $email['title']);
        $this->assertSame('pending_rate', $email['email_view']);
        $this->assertSame(config('app.url').'/app/profile/me#0', $email['url']);
    }

    public function test_to_email_falls_back_to_unknown_destination_without_trip(): void
    {
        $notification = new PendingRateNotification;
        $unknown = __('notifications.destination_unknown');

        $email = $notification->toEmail(null);

        $this->assertSame(__('notifications.pending_rate.title', ['destination' => $unknown]), $email['title']);
    }

    public function test_to_string_get_extras_and_to_push_return_expected_static_payloads(): void
    {
        $notification = new PendingRateNotification;

        $this->assertSame(__('notifications.pending_rate.message'), $notification->toString());
        $this->assertSame(['type' => 'my-trips'], $notification->getExtras());

        $push = $notification->toPush(null, null);
        $this->assertSame(__('notifications.pending_rate.message'), $push['message']);
        $this->assertSame('/my-trips', $push['url']);
        $this->assertArrayHasKey('image', $push);
    }
}
