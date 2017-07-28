<?php

namespace STS\Notifications;

use  STS\Services\Notifications\BaseNotification;
use  STS\Services\Notifications\Channels\MailChannel;
use  STS\Services\Notifications\Channels\PushChannel;
use  STS\Services\Notifications\Channels\DatabaseChannel;

class RequestPassengerNotification extends BaseNotification
{
    protected $via = [DatabaseChannel::class, MailChannel::class, PushChannel::class];

    public function toEmail($user)
    {
        return [
            'title' => 'Nueva solicitud para subirse a uno de tus viajes.',
            'email_view' => 'passenger_email',
            'type' => 'request',
        ];
    }

    public function toString()
    {
        return $this->getAttribute('from')->name.' quiere subirse a uno de tus viajes.';
    }

    public function getExtras()
    {
        return [
            'type' => 'trip',
            'trip_id' => $this->getAttribute('trip')->id,
        ];
    }

    public function toPush($user, $device)
    {
        $trip = $this->getAttribute('trip');

        return [
            'message' => $this->getAttribute('from')->name.' quiere subirse a uno de tus viajes.',
            'url' => 'passenger',
            'extras' => [
                'id' => $trip->id,
            ],
        ];
    }
}
