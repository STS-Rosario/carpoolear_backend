<?php

namespace STS\Notifications;

use  STS\Services\Notifications\BaseNotification;
use  STS\Services\Notifications\Channels\MailChannel;
use  STS\Services\Notifications\Channels\PushChannel;
use  STS\Services\Notifications\Channels\DatabaseChannel;
use  STS\Services\Notifications\Channels\FacebookChannel;

class AutoRequestPassengerNotification extends BaseNotification
{
    protected $via = [
        DatabaseChannel::class, 
        MailChannel::class, 
        PushChannel::class, 
        // FacebookChannel::class
    ];

    public function toEmail($user)
    {
        return [
            'title' => $this->getAttribute('from')->name.' desea subirse a uno de tus viajes.',
            'from' => $this->getAttribute('from')->name,
            'email_view' => 'passenger_autorequest',
            'type' => 'request',
            'url' =>  config('app.url').'/app/profile/me#0',
            'name_app' => config('carpoolear.name_app'),
            'domain' => config('app.url')
        ];
    }

    public function toString()
    {
        $from = $this->getAttribute('from');
        if (is_object($from)) {
            return $from->name.' se ha subido a uno de tus viajes.';
        } else {
            return 'Un pasajero se ha subido a uno de tus viajes.';
        }
    }

    public function getExtras()
    {
        $trip = $this->getAttribute('trip');

        return [
            'type' => 'my-trips',
            'trip_id' => isset($trip) && is_object($trip) ? $trip->id : 0,
        ];
    }

    public function toPush($user, $device)
    {
        $trip = $this->getAttribute('trip');

        return [
            'message' => $this->getAttribute('from')->name.' se ha subido a uno de tus viajes.',
            'url' => 'my-trips',
            'extras' => [
                'id' => $trip->id,
            ],
            'image' => 'https://carpoolear.com.ar/app/static/img/carpoolear_logo.png',
        ];
    }
}
