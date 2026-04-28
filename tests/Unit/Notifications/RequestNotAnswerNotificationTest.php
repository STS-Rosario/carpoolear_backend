<?php

namespace Tests\Unit\Notifications;

use STS\Models\Trip;
use STS\Models\User;
use STS\Notifications\RequestNotAnswerNotification;
use Tests\TestCase;

class RequestNotAnswerNotificationTest extends TestCase
{
    public function test_to_email_uses_trip_url_when_trip_is_present(): void
    {
        $trip = Trip::factory()->create();
        $notification = new RequestNotAnswerNotification;
        $notification->setAttribute('trip', $trip);

        $email = $notification->toEmail(null);

        $this->assertSame(__('notifications.request_not_answer.title'), $email['title']);
        $this->assertSame('request_not_answer', $email['email_view']);
        $this->assertSame(config('app.url').'/app/trips/'.$trip->id, $email['url']);
    }

    public function test_to_string_uses_sender_name_or_fallback_when_missing(): void
    {
        $from = User::factory()->create(['name' => 'Responder']);
        $notification = new RequestNotAnswerNotification;
        $notification->setAttribute('from', $from);

        $this->assertSame(
            __('notifications.request_not_answer.message', ['name' => 'Responder']),
            $notification->toString()
        );

        $fallback = new RequestNotAnswerNotification;
        $this->assertSame(
            __('notifications.request_not_answer.message', ['name' => __('notifications.someone')]),
            $fallback->toString()
        );
    }

    public function test_get_extras_and_push_include_trip_data_or_null_fallbacks(): void
    {
        $trip = Trip::factory()->create();
        $notification = new RequestNotAnswerNotification;
        $notification->setAttribute('trip', $trip);

        $extras = $notification->getExtras();
        $push = $notification->toPush(null, null);

        $this->assertSame('my-trips', $extras['type']);
        $this->assertSame($trip->id, $extras['trip_id']);
        $this->assertSame('/trips/'.$trip->id, $push['url']);
        $this->assertSame($trip->id, $push['extras']['id']);
        $this->assertSame(__('notifications.request_not_answer.push_message'), $push['message']);

        $noTrip = new RequestNotAnswerNotification;
        $this->assertNull($noTrip->getExtras()['trip_id']);
        $this->assertNull($noTrip->toPush(null, null)['extras']['id']);
    }
}
