<?php

namespace STS\Notifications;

use  STS\Services\Notifications\BaseNotification;
use  STS\Services\Notifications\Channels\MailChannel;
use  STS\Services\Notifications\Channels\PushChannel;
use  STS\Services\Notifications\Channels\DatabaseChannel;
use  STS\Services\Notifications\Channels\FacebookChannel;

class RequestRemainderNotification extends BaseNotification
{
    protected $via = [
        DatabaseChannel::class, 
        MailChannel::class, 
        PushChannel::class,
    ];

    public function toEmail($user)
    {
        $trip = $this->getAttribute('trip');
        $from = $this->getAttribute('from');
        $senderName = $from ? $from->name : 'Alguien';
        $tripDate = $trip ? $trip->trip_date : 'fecha no disponible';

        return [
            'title' => 'Tienes solicitudes pendientes de contestar.',
            'email_view' => 'request_remainder',
            'url' =>  config('app.url').'/app/profile/me#0',
            'name_app' => config('carpoolear.name_app'),
            'domain' => config('app.url')
        ];
    }

    public function toString()
    {
        return 'Tienes solicitudes pendientes de contestar.';
    }

    public function getExtras()
    {
        $trip = $this->getAttribute('trip');
        return [
            'type' => 'trip',
            'trip_id' => $trip ? $trip->id : null,
        ];
    }

    public function toPush($user, $device)
    {
        $trip = $this->getAttribute('trip');

        return [
            'message' => 'Tienes solicitudes pendientes de contestar.',
            'url' => 'trips/'.($trip ? $trip->id : ''),
            'extras' => [
                'id' => $trip ? $trip->id : null,
            ],
            'image' => 'https://carpoolear.com.ar/app/static/img/carpoolear_logo.png',
        ];
    }
}
