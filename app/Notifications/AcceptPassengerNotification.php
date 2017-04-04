<?php

namespace STS\Notifications;

use  STS\Services\Notifications\BaseNotification;
use  STS\Services\Notifications\Channels\MailChannel;
use  STS\Services\Notifications\Channels\DatabaseChannel;

class AcceptPassengerNotification extends BaseNotification
{
    protected $via = [DatabaseChannel::class, MailChannel::class];

    public function toEmail($user)
    {
        return [
            'title' => $this->getAttribute('from')->name . ' ha aceptado tu solicitud.',
            'email_view' => 'passenger_email',
            'type' => 'accept'
        ];
    }

    public function toString()
    {
        return $this->getAttribute('from')->name . ' ha aceptado tu solicitud.';
    }
}