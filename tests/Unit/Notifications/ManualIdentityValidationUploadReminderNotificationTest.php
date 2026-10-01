<?php

namespace Tests\Unit\Notifications;

use STS\Notifications\ManualIdentityValidationUploadReminderNotification;
use STS\Services\Notifications\Channels\DatabaseChannel;
use STS\Services\Notifications\Channels\PushChannel;
use Tests\TestCase;

class ManualIdentityValidationUploadReminderNotificationTest extends TestCase
{
    public function test_via_contains_database_and_push_channels(): void
    {
        $notification = new ManualIdentityValidationUploadReminderNotification;

        $this->assertSame([
            DatabaseChannel::class,
            PushChannel::class,
        ], $notification->getVia());
    }

    public function test_to_string_returns_upload_reminder_message_in_argentine_spanish(): void
    {
        app()->setLocale('arg');
        $notification = new ManualIdentityValidationUploadReminderNotification;

        $this->assertSame(
            'Pagaste la verificación manual, no te olvides de subir las imágenes así te verificamos la cuenta. ¿Tuviste problemas? Entrá al Menú -> Mesa de ayuda y te ayudamos',
            $notification->toString()
        );
    }

    public function test_get_extras_returns_manual_validation_type_and_request_id(): void
    {
        $notification = new ManualIdentityValidationUploadReminderNotification;
        $notification->setAttribute('request_id', 42);

        $this->assertSame([
            'type' => 'identity_validation_manual',
            'request_id' => 42,
        ], $notification->getExtras());
    }

    public function test_to_push_builds_manual_validation_upload_url(): void
    {
        app()->setLocale('arg');
        $notification = new ManualIdentityValidationUploadReminderNotification;
        $notification->setAttribute('request_id', 42);

        $push = $notification->toPush(null, null);

        $this->assertSame(
            'Pagaste la verificación manual, no te olvides de subir las imágenes así te verificamos la cuenta. ¿Tuviste problemas? Entrá al Menú -> Mesa de ayuda y te ayudamos',
            $push['message']
        );
        $this->assertSame('/app/setting/identity-validation/manual?request_id=42', $push['url']);
        $this->assertSame('identity_validation_manual', $push['type']);
        $this->assertSame(42, $push['extras']['request_id']);
        $this->assertSame('https://carpoolear.com.ar/app/static/img/carpoolear_logo.png', $push['image']);
    }
}
