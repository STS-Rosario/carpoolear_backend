<?php

namespace Tests\Unit\Notifications;

use STS\Models\Trip;
use STS\Models\User;
use STS\Notifications\UpdateTripNotification;
use Tests\TestCase;

class UpdateTripNotificationTest extends TestCase
{
    public function test_to_email_uses_sender_and_trip_when_present(): void
    {
        $from = User::factory()->create(['name' => 'Trip Updater']);
        $trip = Trip::factory()->create(['user_id' => $from->id]);
        $notification = new UpdateTripNotification;
        $notification->setAttribute('from', $from);
        $notification->setAttribute('trip', $trip);

        $email = $notification->toEmail(null);

        $this->assertSame(__('notifications.update_trip.title', ['name' => 'Trip Updater']), $email['title']);
        $this->assertSame('update_trip', $email['email_view']);
        $this->assertSame(config('app.url').'/app/trips/'.$trip->id, $email['url']);
    }

    public function test_to_string_and_push_fallback_to_someone_when_sender_is_missing(): void
    {
        $notification = new UpdateTripNotification;

        $expected = __('notifications.update_trip.message', ['name' => __('notifications.someone')]);
        $this->assertSame($expected, $notification->toString());

        $push = $notification->toPush(null, null);
        $this->assertSame($expected, $push['message']);
        $this->assertSame('/trips/', $push['url']);
        $this->assertNull($push['extras']['id']);
    }

    public function test_get_extras_and_push_include_trip_id_when_trip_exists(): void
    {
        $trip = Trip::factory()->create();
        $notification = new UpdateTripNotification;
        $notification->setAttribute('trip', $trip);

        $extras = $notification->getExtras();
        $push = $notification->toPush(null, null);

        $this->assertSame('trip', $extras['type']);
        $this->assertSame($trip->id, $extras['trip_id']);
        $this->assertSame($trip->id, $push['extras']['id']);
    }
}
