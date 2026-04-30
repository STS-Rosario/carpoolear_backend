<?php

namespace Tests\Unit\Notifications;

use STS\Models\Trip;
use STS\Models\User;
use STS\Notifications\AutoCancelPassengerRequestIfRequestLimitedNotification;
use STS\Services\Notifications\NotificationServices;
use Tests\TestCase;

class AutoCancelPassengerRequestIfRequestLimitedNotificationTest extends TestCase
{
    public function test_to_email_keeps_url_path_when_trip_id_is_present(): void
    {
        $this->mock(NotificationServices::class)->shouldIgnoreMissing();

        $trip = Trip::factory()->create(['to_town' => 'La Plata']);

        $notification = new AutoCancelPassengerRequestIfRequestLimitedNotification;
        $notification->setAttribute('trip', $trip);

        $payload = $notification->toEmail(User::factory()->make());

        $this->assertStringContainsString('/app/trips/'.$trip->id, $payload['url']);
        $this->assertStringContainsString('La Plata', $payload['title']);
    }

    public function test_to_email_uses_unknown_destination_and_empty_trip_segment_when_trip_missing(): void
    {
        $this->mock(NotificationServices::class)->shouldIgnoreMissing();

        $notification = new AutoCancelPassengerRequestIfRequestLimitedNotification;
        $notification->setAttribute('trip', null);

        $payload = $notification->toEmail(User::factory()->make());

        $this->assertStringEndsWith('/app/trips/', $payload['url']);
        $this->assertNotSame('', (string) ($payload['title'] ?? ''));
    }
}
