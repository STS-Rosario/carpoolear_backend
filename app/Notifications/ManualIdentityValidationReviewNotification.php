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
        return __($this->messageTranslationKey());
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

        return [
            'message' => __($this->messageTranslationKey()),
            'url' => '/app/identity-validation',
            'type' => 'identity_validation',
            'extras' => [
                'action' => $action,
            ],
            'image' => 'https://carpoolear.com.ar/app/static/img/carpoolear_logo.png',
        ];
    }

    private function messageTranslationKey(): string
    {
        if ($this->getAttribute('action') === 'rejected') {
            return 'notifications.manual_identity_validation.rejected';
        }

        return 'notifications.manual_identity_validation.approved';
    }
}
