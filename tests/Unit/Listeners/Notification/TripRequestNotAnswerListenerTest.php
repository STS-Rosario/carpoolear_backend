<?php

namespace Tests\Unit\Listeners\Notification;

use STS\Events\Trip\Alert\RequestNotAnswer;
use STS\Listeners\Notification\TripRequestNotAnswer;
use STS\Models\Trip;
use STS\Models\User;
use STS\Notifications\RequestNotAnswerNotification;
use STS\Services\Notifications\NotificationServices;
use Tests\TestCase;

class TripRequestNotAnswerListenerTest extends TestCase
{
    public function test_handle_skips_notification_when_recipient_is_null(): void
    {
        $this->mock(NotificationServices::class)->shouldNotReceive('send');

        $trip = Trip::factory()->create();

        (new TripRequestNotAnswer)->handle(new RequestNotAnswer($trip, null));
    }

    public function test_handle_notifies_recipient_with_trip_on_all_channels(): void
    {
        $trip = Trip::factory()->create();
        $recipient = User::factory()->create();

        $this->mock(NotificationServices::class)
            ->shouldReceive('send')
            ->times(2)
            ->withArgs(function ($notification, $users, $channel) use ($trip, $recipient) {
                if (! $notification instanceof RequestNotAnswerNotification) {
                    return false;
                }

                return $notification->getAttribute('trip')->is($trip)
                    && $users instanceof User
                    && $users->is($recipient)
                    && is_string($channel);
            });

        (new TripRequestNotAnswer)->handle(new RequestNotAnswer($trip, $recipient));
    }
}
