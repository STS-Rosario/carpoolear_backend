<?php

namespace Tests\Unit\Notifications;

use STS\Notifications\ManualIdentityValidationReviewNotification;
use STS\Services\Notifications\Channels\DatabaseChannel;
use STS\Services\Notifications\Channels\PushChannel;
use Tests\TestCase;

class ManualIdentityValidationReviewNotificationTest extends TestCase
{
    public function test_via_contains_database_and_push_channels(): void
    {
        $notification = new ManualIdentityValidationReviewNotification;

        $this->assertSame([
            DatabaseChannel::class,
            PushChannel::class,
        ], $notification->getVia());
    }

    public function test_to_string_returns_approved_message_when_action_is_approved(): void
    {
        $notification = new ManualIdentityValidationReviewNotification;
        $notification->setAttribute('action', 'approved');

        $this->assertSame(
            __('notifications.manual_identity_validation.approved'),
            $notification->toString()
        );
    }

    public function test_to_string_returns_rejected_message_when_action_is_rejected(): void
    {
        $notification = new ManualIdentityValidationReviewNotification;
        $notification->setAttribute('action', 'rejected');

        $this->assertSame(
            __('notifications.manual_identity_validation.rejected'),
            $notification->toString()
        );
    }

    public function test_get_extras_returns_identity_validation_type(): void
    {
        $notification = new ManualIdentityValidationReviewNotification;
        $notification->setAttribute('action', 'approved');

        $this->assertSame([
            'type' => 'identity_validation',
            'action' => 'approved',
        ], $notification->getExtras());
    }

    public function test_to_push_builds_identity_validation_url_and_extras(): void
    {
        $notification = new ManualIdentityValidationReviewNotification;
        $notification->setAttribute('action', 'rejected');

        $push = $notification->toPush(null, null);

        $this->assertSame(
            __('notifications.manual_identity_validation.rejected'),
            $push['message']
        );
        $this->assertSame('/app/identity-validation', $push['url']);
        $this->assertSame('identity_validation', $push['type']);
        $this->assertSame('rejected', $push['extras']['action']);
        $this->assertSame('https://carpoolear.com.ar/app/static/img/carpoolear_logo.png', $push['image']);
    }
}
