<?php

namespace Tests\Unit\Notifications;

use STS\Models\Passenger;
use STS\Models\Trip;
use STS\Models\User;
use STS\Notifications\AcceptPassengerNotification;
use Tests\TestCase;

class AcceptPassengerNotificationTest extends TestCase
{
    public function test_to_email_and_push_use_sender_and_trip_when_present(): void
    {
        $from = User::factory()->create(['name' => 'Driver Name']);
        $trip = Trip::factory()->create(['user_id' => $from->id]);
        $notification = new AcceptPassengerNotification;
        $notification->setAttribute('from', $from);
        $notification->setAttribute('trip', $trip);

        $email = $notification->toEmail(null);
        $push = $notification->toPush(null, null);

        $this->assertSame(__('notifications.accept_passenger.title', ['name' => 'Driver Name']), $email['title']);
        $this->assertSame('accept_passenger', $email['email_view']);
        $this->assertSame(config('app.url').'/app/trips/'.$trip->id, $email['url']);
        $this->assertSame('/trips/'.$trip->id, $push['url']);
        $this->assertSame($trip->id, $push['extras']['id']);
    }

    public function test_to_string_and_push_fallback_to_someone_when_sender_is_missing(): void
    {
        $notification = new AcceptPassengerNotification;

        $expectedMessage = __('notifications.accept_passenger.message', ['name' => __('notifications.someone')]);
        $this->assertSame($expectedMessage, $notification->toString());

        $push = $notification->toPush(null, null);
        $this->assertSame($expectedMessage, $push['message']);
        $this->assertSame('/trips/', $push['url']);
        $this->assertNull($push['extras']['id']);
    }

    public function test_get_extras_returns_trip_default_when_no_matching_token_request(): void
    {
        $from = User::factory()->create();
        $trip = Trip::factory()->create(['user_id' => $from->id]);
        $notification = new AcceptPassengerNotification;
        $notification->setAttribute('trip', $trip);

        $extras = $notification->getExtras();

        $this->assertSame('trip', $extras['type']);
        $this->assertSame($trip->id, $extras['trip_id']);
    }

    public function test_get_extras_returns_my_trips_when_token_user_has_waiting_payment_request(): void
    {
        $from = User::factory()->create();
        $to = User::factory()->create();
        $trip = Trip::factory()->create(['user_id' => $from->id]);
        Passenger::factory()->create([
            'trip_id' => $trip->id,
            'user_id' => $to->id,
            'request_state' => Passenger::STATE_WAITING_PAYMENT,
            'passenger_type' => Passenger::TYPE_PASAJERO,
        ]);

        $notification = new AcceptPassengerNotification;
        $notification->setAttribute('trip', $trip->fresh());
        $notification->setAttribute('token', $to);

        $extras = $notification->getExtras();

        $this->assertSame('my-trips', $extras['type']);
        $this->assertSame($trip->id, $extras['trip_id']);
    }
}
