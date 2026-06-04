<?php

namespace STS\Notifications;

use STS\Services\Notifications\BaseNotification;
use STS\Services\Notifications\Channels\DatabaseChannel;
use STS\Services\Notifications\Channels\PushChannel;

class ManualIdentityValidationReviewNotification extends BaseNotification
{
    protected $via = [
        DatabaseChannel::class,
        PushChannel::class,
    ];

    public function toString()
    {
        $action = $this->getAttribute('action');

        if ($action === 'rejected') {
            return __('notifications.manual_identity_validation.rejected');
        }

        return __('notifications.manual_identity_validation.approved');
    }

    public function getExtras()
    {
        return [
            'type' => 'identity_validation',
            'action' => $this->getAttribute('action'),
        ];
    }

    public function toPush($user, $device)
    {
        $action = $this->getAttribute('action');
        $messageKey = $action === 'rejected'
            ? 'notifications.manual_identity_validation.rejected'
            : 'notifications.manual_identity_validation.approved';

        return [
            'message' => __($messageKey),
            'url' => '/app/identity-validation',
            'type' => 'identity_validation',
            'extras' => [
                'action' => $action,
            ],
            'image' => 'https://carpoolear.com.ar/app/static/img/carpoolear_logo.png',
        ];
    }
}
