<?php

namespace STS\Notifications;

use  STS\Services\Notifications\BaseNotification;
use  STS\Services\Notifications\Channels\MailChannel;
use  STS\Services\Notifications\Channels\DatabaseChannel;

class NewMessageNotification extends BaseNotification
{
    protected $via = [DatabaseChannel::class, MailChannel::class];

    public function toEmail($user)
    {
        return [
            'title' => $this->getAttribute('from')->name . 'te ha enviado un mensaje.',
            'email_view' => 'new_message', 
        ];
    }

    public function toString()
    {
        return $this->getAttribute('from')->name . 'te ha enviado un mensaje.';
    }
}
