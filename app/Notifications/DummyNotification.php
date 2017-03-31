<?php

namespace STS\Notifications;

use  STS\Services\Notifications\BaseNotification;
use  STS\Services\Notifications\Channels\MailChannel;
use  STS\Services\Notifications\Channels\DatabaseChannel;

class DummyNotification extends BaseNotification
{
    protected $via = [DatabaseChannel::class, MailChannel::class];

    public function toEmail($user)
    {
        return [
            'title' => 'Dummy Title',
            'email_view' => 'dummy',
        ];
    }

    public function toString()
    {
        return 'Dummy Notification '.$this->getAttribute('dummy');
    }
}
