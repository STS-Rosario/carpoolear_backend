<?php

namespace STS\Notifications;

use STS\Services\Notifications\BaseNotification;
use STS\Services\Notifications\Channels\DatabaseChannel;
use STS\Services\Notifications\Channels\PushChannel;

class ManualIdentityValidationUploadReminderNotification extends BaseNotification
{
    protected $via = [
        DatabaseChannel::class,
        PushChannel::class,
    ];

    public function toString()
    {
        return __('notifications.manual_identity_validation.upload_photos_reminder');
    }

    public function getExtras()
    {
        return [
            'type' => 'identity_validation_manual',
            'request_id' => (int) $this->getAttribute('request_id'),
        ];
    }

    public function toPush($user, $device)
    {
        $requestId = (int) $this->getAttribute('request_id');

        return [
            'message' => __('notifications.manual_identity_validation.upload_photos_reminder'),
            'url' => '/app/setting/identity-validation/manual?request_id='.$requestId,
            'type' => 'identity_validation_manual',
            'extras' => [
                'request_id' => $requestId,
            ],
            'image' => 'https://carpoolear.com.ar/app/static/img/carpoolear_logo.png',
        ];
    }
}
