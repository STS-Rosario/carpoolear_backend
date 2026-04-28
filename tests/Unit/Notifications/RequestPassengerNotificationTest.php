<?php

namespace Tests\Unit\Notifications;

use STS\Models\Trip;
use STS\Models\User;
use STS\Notifications\RequestPassengerNotification;
use Tests\TestCase;

class RequestPassengerNotificationTest extends TestCase
{
    public function test_to_email_uses_sender_and_trip_when_present(): void
    {
        $from = User::factory()->create(['name' => 'Passenger Requester']);
        $trip = Trip::factory()->create();
        $notification = new RequestPassengerNotification;
        $notification->setAttribute('from', $from);
        $notification->setAttribute('trip', $trip);

        $email = $notification->toEmail(null);

        $this->assertSame(__('notifications.request_passenger.title', ['name' => 'Passenger Requester']), $email['title']);
        $this->assertSame('passenger_request', $email['email_view']);
        $this->assertSame('request', $email['type']);
        $this->assertSame(config('app.url').'/app/trips/'.$trip->id, $email['url']);
    }

    public function test_to_string_and_push_fallback_to_someone_when_sender_is_missing(): void
    {
        $notification = new RequestPassengerNotification;

        $expectedMessage = __('notifications.request_passenger.message', ['name' => __('notifications.someone')]);
        $this->assertSame($expectedMessage, $notification->toString());

        $push = $notification->toPush(null, null);
        $this->assertSame($expectedMessage, $push['message']);
        $this->assertSame('/my-trips', $push['url']);
        $this->assertNull($push['extras']['id']);
    }

    public function test_get_extras_and_push_include_trip_id_when_available(): void
    {
        $trip = Trip::factory()->create();
        $notification = new RequestPassengerNotification;
        $notification->setAttribute('trip', $trip);

        $extras = $notification->getExtras();
        $push = $notification->toPush(null, null);

        $this->assertSame('my-trips', $extras['type']);
        $this->assertSame($trip->id, $extras['trip_id']);
        $this->assertSame($trip->id, $push['extras']['id']);
    }
}
