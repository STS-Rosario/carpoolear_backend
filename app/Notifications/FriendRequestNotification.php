<?php

namespace STS\Notifications;

use  STS\Services\Notifications\BaseNotification;
use  STS\Services\Notifications\Channels\MailChannel;
use  STS\Services\Notifications\Channels\DatabaseChannel;
use  STS\Services\Notifications\Channels\PushChannel;

class FriendRequestNotification extends BaseNotification
{
    protected $via = [DatabaseChannel::class, MailChannel::class];

    public function toEmail($user)
    {
        return [
            'title' => 'Nueva solicitud de amistad',
            'email_view' => 'friends_email',
            'type' => 'request'
        ];
    }

    public function toString()
    {
        return $this->getAttribute('from')->name . ' te ha enviado una solicitud de amistad.';
    }
}
