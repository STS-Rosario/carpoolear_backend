<?php

namespace STS\Notifications;

use STS\Services\Notifications\BaseNotification;
use STS\Services\Notifications\Channels\DatabaseChannel;
use STS\Services\Notifications\Channels\PushChannel;

class ManualIdentityValidationPhotosPurgedNotification extends BaseNotification
{
    protected $via = [
        DatabaseChannel::class,
        PushChannel::class,
    ];

    public function toString()
    {
        return __('notifications.manual_identity_validation.photos_purged');
    }

    public function getExtras()
    {
        return [
            'type' => 'identity_validation_manual',
            'request_id' => (int) $this->getAttribute('request_id'),
            'resubmit' => '1',
        ];
    }

    public function toPush($user, $device)
    {
        $requestId = (int) $this->getAttribute('request_id');

        return [
            'message' => __('notifications.manual_identity_validation.photos_purged'),
            'url' => '/app/setting/identity-validation/manual?request_id='.$requestId.'&resubmit=1',
            'type' => 'identity_validation_manual',
            'extras' => [
                'request_id' => $requestId,
                'resubmit' => '1',
            ],
            'image' => 'https://carpoolear.com.ar/app/static/img/carpoolear_logo.png',
        ];
    }
}
