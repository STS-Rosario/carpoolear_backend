<?php

namespace STS\Notifications;

use  STS\Services\Notifications\BaseNotification;
use  STS\Services\Notifications\Channels\MailChannel;
use  STS\Services\Notifications\Channels\PushChannel;
use  STS\Services\Notifications\Channels\DatabaseChannel;
use  STS\Services\Notifications\Channels\FacebookChannel;

class RequestPassengerNotification extends BaseNotification
{
    protected $via = [DatabaseChannel::class, MailChannel::class, PushChannel::class, FacebookChannel::class];

    public function toEmail($user)
    {
        return [
            'title' => $this->getAttribute('from')->name.' desea subirse a uno de tus viajes.',
            'from' => $this->getAttribute('from')->name,
            'email_view' => 'passenger_request',
            'type' => 'request',
            'url' =>  config('app.url').'/app/profile/me#0',
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
