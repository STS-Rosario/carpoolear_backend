<?php

namespace Tests\Unit\Notifications;

use STS\Notifications\ManualIdentityValidationPhotosPurgedNotification;
use STS\Services\Notifications\Channels\DatabaseChannel;
use STS\Services\Notifications\Channels\PushChannel;
use Tests\TestCase;

class ManualIdentityValidationPhotosPurgedNotificationTest extends TestCase
{
    public function test_via_contains_database_and_push_channels(): void
    {
        $notification = new ManualIdentityValidationPhotosPurgedNotification;

        $this->assertSame([
            DatabaseChannel::class,
            PushChannel::class,
        ], $notification->getVia());
    }

    public function test_to_string_returns_photos_purged_message(): void
    {
        $notification = new ManualIdentityValidationPhotosPurgedNotification;

        $this->assertSame(
            __('notifications.manual_identity_validation.photos_purged'),
            $notification->toString()
        );
    }

    public function test_get_extras_returns_manual_validation_type_and_request_id(): void
    {
        $notification = new ManualIdentityValidationPhotosPurgedNotification;
        $notification->setAttribute('request_id', 42);

        $this->assertSame([
            'type' => 'identity_validation_manual',
            'request_id' => 42,
            'resubmit' => '1',
        ], $notification->getExtras());
    }

    public function test_to_push_builds_manual_validation_url_with_request_id(): void
    {
        $notification = new ManualIdentityValidationPhotosPurgedNotification;
        $notification->setAttribute('request_id', 42);

        $push = $notification->toPush(null, null);

        $this->assertSame(
            __('notifications.manual_identity_validation.photos_purged'),
            $push['message']
        );
        $this->assertSame('/app/setting/identity-validation/manual?request_id=42&resubmit=1', $push['url']);
        $this->assertSame('identity_validation_manual', $push['type']);
        $this->assertSame(42, $push['extras']['request_id']);
        $this->assertSame('1', $push['extras']['resubmit']);
    }
}
