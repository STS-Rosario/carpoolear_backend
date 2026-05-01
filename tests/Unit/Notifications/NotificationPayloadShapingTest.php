<?php

namespace Tests\Unit\Notifications;

use STS\Models\Trip;
use STS\Models\User;
use STS\Notifications\AcceptPassengerNotification;
use STS\Notifications\AutoCancelRequestIfRequestLimitedNotification;
use STS\Notifications\AutoRequestPassengerNotification;
use STS\Notifications\CancelPassengerNotification;
use STS\Notifications\DeleteTripNotification;
use STS\Notifications\HourLeftNotification;
use STS\Notifications\NewMessageNotification;
use STS\Notifications\NewMessagePushNotification;
use STS\Notifications\RejectPassengerNotification;
use STS\Notifications\RequestNotAnswerNotification;
use STS\Notifications\SubscriptionMatchNotification;
use STS\Notifications\UpdateTripNotification;
use STS\Services\Notifications\NotificationServices;
use Tests\TestCase;

class NotificationPayloadShapingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config([
            'app.url' => 'https://app.test',
            'carpoolear.name_app' => 'CarpTest',
        ]);
        $this->mock(NotificationServices::class)->shouldIgnoreMissing();
    }

    public function test_request_not_answer_to_email_and_push_shape_with_and_without_trip(): void
    {
        $n = new RequestNotAnswerNotification;
        $n->setAttribute('trip', null);
        $email = $n->toEmail(null);
        $this->assertSame(['title', 'email_view', 'url', 'name_app', 'domain'], array_keys($email));
        $this->assertSame('https://app.test/app/trips/', $email['url']);
        $this->assertSame('CarpTest', $email['name_app']);
        $this->assertSame('https://app.test', $email['domain']);

        $push = $n->toPush(null, null);
        $this->assertSame(
            ['message', 'url', 'extras', 'image'],
            array_keys($push)
        );
        $this->assertSame('/trips/', $push['url']);
        $this->assertSame('https://carpoolear.com.ar/app/static/img/carpoolear_logo.png', $push['image']);

        $trip = Trip::factory()->make(['id' => 501]);
        $n2 = new RequestNotAnswerNotification;
        $n2->setAttribute('trip', $trip);
        $this->assertSame('https://app.test/app/trips/501', $n2->toEmail(null)['url']);
        $this->assertSame('/trips/501', $n2->toPush(null, null)['url']);
    }

    public function test_reject_passenger_to_email_includes_type_reason_and_url_keys(): void
    {
        $n = new RejectPassengerNotification;
        $n->setAttribute('trip', null);
        $n->setAttribute('from', null);
        $email = $n->toEmail(null);
        $this->assertSame(
            ['title', 'email_view', 'type', 'reason_message', 'url', 'name_app', 'domain'],
            array_keys($email)
        );
        $this->assertSame('reject', $email['type']);
        $this->assertSame('https://app.test/app/trips/', $email['url']);

        $n->setAttribute('trip', Trip::factory()->make(['id' => 77]));
        $n->setAttribute('from', User::factory()->make(['name' => 'Pat']));
        $this->assertSame('https://app.test/app/trips/77', $n->toEmail(null)['url']);
    }

    public function test_reject_passenger_to_email_with_null_trip_does_not_throw(): void
    {
        $n = new RejectPassengerNotification;
        $n->setAttribute('trip', null);
        $n->setAttribute('from', User::factory()->make());
        $this->assertIsString($n->toEmail(null)['title']);
    }

    public function test_update_delete_accept_to_email_null_trip_returns_full_key_set(): void
    {
        foreach ([new UpdateTripNotification, new DeleteTripNotification, new AcceptPassengerNotification] as $n) {
            $n->setAttribute('trip', null);
            $n->setAttribute('from', User::factory()->make(['name' => 'Alex']));
            $email = $n->toEmail(null);
            $this->assertArrayHasKey('email_view', $email);
            $this->assertArrayHasKey('name_app', $email);
            $this->assertArrayHasKey('domain', $email);
            $this->assertSame('https://app.test/app/trips/', $email['url']);
        }
    }

    public function test_new_message_to_email_and_to_push_use_empty_conversation_segment_when_messages_missing(): void
    {
        $n = new NewMessageNotification;
        $n->setAttribute('from', User::factory()->make());
        $n->setAttribute('messages', null);
        $email = $n->toEmail(null);
        $this->assertStringEndsWith('/app/conversations/', $email['url']);

        $push = $n->toPush(null, null);
        $this->assertSame('/conversations/', $push['url']);
        $this->assertArrayHasKey('image', $push);
    }

    public function test_new_message_push_to_email_uses_empty_conversation_id_segment(): void
    {
        $n = new NewMessagePushNotification;
        $n->setAttribute('from', User::factory()->make());
        $n->setAttribute('messages', null);
        $email = $n->toEmail(null);
        $this->assertStringEndsWith('/app/conversations/', $email['url']);
    }

    public function test_cancel_passenger_to_email_preserves_email_view_and_url_keys(): void
    {
        $n = new CancelPassengerNotification;
        $n->setAttribute('trip', null);
        $n->setAttribute('from', User::factory()->make());
        $n->setAttribute('is_driver', true);
        $email = $n->toEmail(null);
        $this->assertSame('cancel_passenger', $email['email_view']);
        $this->assertArrayHasKey('title', $email);
        $this->assertSame('https://app.test/app/trips/', $email['url']);
    }

    public function test_subscription_match_hour_left_and_auto_request_urls_with_trip_id(): void
    {
        $trip = Trip::factory()->make(['id' => 9001]);

        $sub = new SubscriptionMatchNotification;
        $sub->setAttribute('trip', $trip);
        $this->assertSame('https://app.test/app/trips/9001', $sub->toEmail(null)['url']);

        $hour = new HourLeftNotification;
        $hour->setAttribute('trip', $trip);
        $hour->setAttribute('from', User::factory()->make());
        $this->assertSame('https://app.test/app/trips/9001', $hour->toEmail(null)['url']);

        $auto = new AutoRequestPassengerNotification;
        $auto->setAttribute('trip', $trip);
        $auto->setAttribute('from', User::factory()->make());
        $this->assertSame('https://app.test/app/trips/9001', $auto->toEmail(null)['url']);

        $cancelReq = new AutoCancelRequestIfRequestLimitedNotification;
        $cancelReq->setAttribute('trip', $trip);
        $this->assertSame('https://app.test/app/trips/9001', $cancelReq->toEmail(null)['url']);
    }
}
