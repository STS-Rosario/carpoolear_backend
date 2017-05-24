<?php

namespace STS\Notifications;

use  STS\Services\Notifications\BaseNotification;
use  STS\Services\Notifications\Channels\MailChannel;
use  STS\Services\Notifications\Channels\DatabaseChannel;
use  STS\Services\Notifications\Channels\PushChannel;

class FriendAcceptNotification extends BaseNotification
{
    protected $via = [DatabaseChannel::class, MailChannel::class, PushChannel::class];

    public function toEmail($user)
    {
        return [
            'title' => $this->getAttribute('from')->name.'ha aceptado tu solicitud de amistad.',
            'email_view' => 'friends_email',
            'type' => 'accept',
        ];
    }

    public function toString()
    {
        return $this->getAttribute('from')->name.' ha aceptado tu solicitud de amistad.';
    }

    public function getExtras()
    {
        return [
            'type' => 'friends',
        ];
    }

    public function toPush($user, $device) {
        $from = $this->getAttribute('from');  

        return [
            'message' => $from->name.' ha aceptado tu solicitud de amistad.',
            'url' => 'friend',
            'extras' => [
                'id' => $from->id
            ]
        ];
    }
}
