<?php

namespace STS\Notifications;

use  STS\Services\Notifications\BaseNotification;
use  STS\Services\Notifications\Channels\MailChannel;
use  STS\Services\Notifications\Channels\PushChannel;
use  STS\Services\Notifications\Channels\DatabaseChannel;
use  STS\Services\Notifications\Channels\FacebookChannel;

class FriendRejectNotification extends BaseNotification
{
    protected $via = [DatabaseChannel::class, MailChannel::class, PushChannel::class, FacebookChannel::class];

    public function toEmail($user)
    {
        return [
            'title' => $this->getAttribute('from')->name.' ha rechazado tu solicitud de amistad.',
            'email_view' => 'friends_email',
            'type' => 'reject',
            'message_mail' => 'rechazado',
            'name_app' => config('carpoolear.name_app'),
            'domain' => config('app.url')
        ];
    }

    public function toString()
    {
        return $this->getAttribute('from')->name.' ha rechazado tu solicitud de amistad.';
    }

    public function getExtras()
    {
        return [
            'type' => 'friends',
        ];
    }

    public function toPush($user, $device)
    {
        $from = $this->getAttribute('from');

        return [
            'message' => $from->name.' ha rechazado tu solicitud de amistad.',
            'url' => 'setting/friends',
            'extras' => [
                'id' => $from->id,
            ],
            'image' => 'https://carpoolear.com.ar/app/static/img/carpoolear_logo.png',
        ];
    }
}
