<?php

namespace Tests\Unit\Notifications;

use STS\Models\Trip;
use STS\Models\User;
use STS\Notifications\DeleteTripNotification;
use Tests\TestCase;

class DeleteTripNotificationTest extends TestCase
{
    public function test_to_email_uses_sender_and_trip_data_when_present(): void
    {
        $from = User::factory()->create(['name' => 'Laura Driver']);
        $trip = Trip::factory()->create(['user_id' => $from->id]);
        $notification = new DeleteTripNotification;
        $notification->setAttribute('from', $from);
        $notification->setAttribute('trip', $trip);

        $email = $notification->toEmail(null);

        $this->assertSame(__('notifications.delete_trip.title', ['name' => 'Laura Driver']), $email['title']);
        $this->assertSame('delete_trip', $email['email_view']);
        $this->assertSame(config('app.url').'/app/trips/'.$trip->id, $email['url']);
    }

    public function test_to_string_and_push_fallback_to_someone_when_from_is_missing(): void
    {
        $notification = new DeleteTripNotification;

        $expectedMessage = __('notifications.delete_trip.message', ['name' => __('notifications.someone')]);
        $this->assertSame($expectedMessage, $notification->toString());

        $push = $notification->toPush(null, null);
        $this->assertSame($expectedMessage, $push['message']);
        $this->assertSame('/trips/', $push['url']);
        $this->assertNull($push['extras']['id']);
    }

    public function test_get_extras_and_push_include_trip_id_when_available(): void
    {
        $trip = Trip::factory()->create();
        $notification = new DeleteTripNotification;
        $notification->setAttribute('trip', $trip);

        $extras = $notification->getExtras();
        $push = $notification->toPush(null, null);

        $this->assertSame('trip', $extras['type']);
        $this->assertSame($trip->id, $extras['trip_id']);
        $this->assertSame($trip->id, $push['extras']['id']);
    }
}
