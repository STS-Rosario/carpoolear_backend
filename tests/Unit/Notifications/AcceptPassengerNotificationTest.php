<?php

namespace Tests\Unit\Notifications;

use STS\Models\Passenger;
use STS\Models\Trip;
use STS\Models\User;
use STS\Notifications\AcceptPassengerNotification;
use STS\Services\Notifications\Channels\DatabaseChannel;
use STS\Services\Notifications\Channels\MailChannel;
use STS\Services\Notifications\Channels\PushChannel;
use Tests\TestCase;

class AcceptPassengerNotificationTest extends TestCase
{
    public function test_via_contains_database_mail_and_push_channels(): void
    {
        $notification = new AcceptPassengerNotification;

        $this->assertSame([
            DatabaseChannel::class,
            MailChannel::class,
            PushChannel::class,
        ], $notification->getVia());
    }

    public function test_to_email_and_push_use_sender_and_trip_when_present(): void
    {
        config([
            'carpoolear.name_app' => 'Carpoolear Test',
            'app.url' => 'https://app.test',
        ]);

        $from = User::factory()->create(['name' => 'Driver Name']);
        $trip = Trip::factory()->create(['user_id' => $from->id]);
        $notification = new AcceptPassengerNotification;
        $notification->setAttribute('from', $from);
        $notification->setAttribute('trip', $trip);

        $email = $notification->toEmail(null);
        $push = $notification->toPush(null, null);

        $this->assertSame(__('notifications.accept_passenger.title', ['name' => 'Driver Name']), $email['title']);
        $this->assertSame('accept_passenger', $email['email_view']);
        $this->assertSame('https://app.test/app/trips/'.$trip->id, $email['url']);
        $this->assertSame('Carpoolear Test', $email['name_app']);
        $this->assertSame('https://app.test', $email['domain']);
        $this->assertSame('/trips/'.$trip->id, $push['url']);
        $this->assertSame($trip->id, $push['extras']['id']);
        $this->assertSame('https://carpoolear.com.ar/app/static/img/carpoolear_logo.png', $push['image']);
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
        $this->assertSame('https://carpoolear.com.ar/app/static/img/carpoolear_logo.png', $push['image']);
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
