<?php

namespace STS\Notifications;

use  STS\Services\Notifications\BaseNotification;
use  STS\Services\Notifications\Channels\MailChannel;
use  STS\Services\Notifications\Channels\DatabaseChannel;

class FriendAcceptNotification extends BaseNotification
{
    protected $via = [DatabaseChannel::class, MailChannel::class];

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
}
