<?php

namespace STS\Notifications;

use  STS\Services\Notifications\BaseNotification;
use  STS\Services\Notifications\Channels\MailChannel;
use  STS\Services\Notifications\Channels\PushChannel;
use  STS\Services\Notifications\Channels\DatabaseChannel;
use  STS\Services\Notifications\Channels\FacebookChannel;

class AutoCancelPassengerRequestIfRequestLimitedNotification extends BaseNotification
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
            'title' => 'Se ha retirado automáticamente una solicitud de un pasajero en tu viaje con destino ' . $this->getAttribute('trip')->to_town . ' debido a que se subió a otro viaje con igual destino.',
            'email_view' => 'passenger_autocancel_trip_owner',
            'type' => 'auto_cancel',
            'url' => config('app.url').'/app/trips/'.$this->getAttribute('trip')->id,
            'name_app' => config('carpoolear.name_app'),
            'domain' => config('app.url')
        ];
    }

    public function toString()
    {
        return 'Se ha retirado automáticamente una solicitud de un pasajero en tu viaje con destino ' . $this->getAttribute('trip')->to_town . ' debido a que se subió a otro viaje con igual destino.';
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
            'message' => 'Se ha retirado automáticamente una solicitud de un pasajero en tu viaje con destino ' . $this->getAttribute('trip')->to_town . ' debido a que se subió a otro viaje con igual destino.',
            'url' => 'trips/'.$trip->id,
            'extras' => [
                'id' => $trip->id,
            ],
            'image' => 'https://carpoolear.com.ar/app/static/img/carpoolear_logo.png',
        ];
    }
}
