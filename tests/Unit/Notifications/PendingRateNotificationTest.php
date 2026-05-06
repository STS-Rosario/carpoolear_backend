<?php

namespace Tests\Unit\Notifications;

use STS\Models\Trip;
use STS\Notifications\PendingRateNotification;
use STS\Services\Notifications\Channels\DatabaseChannel;
use STS\Services\Notifications\Channels\MailChannel;
use STS\Services\Notifications\Channels\PushChannel;
use Tests\TestCase;

class PendingRateNotificationTest extends TestCase
{
    public function test_via_contains_database_mail_and_push_channels(): void
    {
        $notification = new PendingRateNotification;

        $this->assertSame([
            DatabaseChannel::class,
            MailChannel::class,
            PushChannel::class,
        ], $notification->getVia());
    }

    public function test_to_email_uses_trip_destination_when_present(): void
    {
        config([
            'carpoolear.name_app' => 'Carpoolear Test',
            'app.url' => 'https://app.test',
        ]);

        $trip = Trip::factory()->create(['to_town' => 'La Plata']);
        $notification = new PendingRateNotification;
        $notification->setAttribute('trip', $trip);

        $email = $notification->toEmail(null);

        $this->assertSame(__('notifications.pending_rate.title', ['destination' => 'La Plata']), $email['title']);
        $this->assertSame('pending_rate', $email['email_view']);
        $this->assertSame('https://app.test/app/profile/me#0', $email['url']);
        $this->assertSame('Carpoolear Test', $email['name_app']);
        $this->assertSame('https://app.test', $email['domain']);
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
        $this->assertSame('https://carpoolear.com.ar/app/static/img/carpoolear_logo.png', $push['image']);
    }
}
