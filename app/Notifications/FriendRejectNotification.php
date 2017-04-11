<?php

namespace STS\Notifications;

use  STS\Services\Notifications\BaseNotification;
use  STS\Services\Notifications\Channels\MailChannel;
use  STS\Services\Notifications\Channels\DatabaseChannel;
use  STS\Services\Notifications\Channels\PushChannel;

class FriendRejectNotification extends BaseNotification
{
    protected $via = [DatabaseChannel::class, MailChannel::class];

    public function toEmail($user)
    {
        return [
            'title' => $this->getAttribute('from')->name . 'ha rechazado tu solicitud de amistad.',
            'email_view' => 'friends_email',
            'type' => 'reject'
        ];
    }

    public function toString()
    {
        return $this->getAttribute('from')->name . ' ha rechazado tu solicitud de amistad.';
    }
}