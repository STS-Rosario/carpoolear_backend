<?php

namespace STS\Notifications;

use  STS\Services\Notifications\BaseNotification;
use  STS\Services\Notifications\Channels\MailChannel;
use  STS\Services\Notifications\Channels\DatabaseChannel;

class RequestPassengerNotification extends BaseNotification
{
    protected $via = [DatabaseChannel::class, MailChannel::class];

    public function toEmail($user)
    {
        return [
            'title' => 'Nueva solicitud de viaje',
            'email_view' => 'passenger_email',
            'type' => 'request'
        ];
    }

    public function toString()
    {
        return $this->getAttribute('from')->name . ' quiere subirse a unos de tus viajes.';
    }
}
