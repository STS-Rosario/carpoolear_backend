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
        $trip = $this->getAttribute('trip');
        $destination = $trip ? $trip->to_town : 'destino desconocido';

        return [
            'title' => 'Se ha retirado automáticamente una solicitud de un pasajero en tu viaje con destino ' . $destination . ' debido a que se subió a otro viaje con igual destino.',
            'email_view' => 'auto_cancel_request',
            'url' => config('app.url').'/app/trips/'.($trip ? $trip->id : ''),
            'name_app' => config('carpoolear.name_app'),
            'domain' => config('app.url')
        ];
    }

    public function toString()
    {
        $trip = $this->getAttribute('trip');
        $destination = $trip ? $trip->to_town : 'destino desconocido';
        return 'Se ha retirado automáticamente una solicitud de un pasajero en tu viaje con destino ' . $destination . ' debido a que se subió a otro viaje con igual destino.';
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
        $destination = $trip ? $trip->to_town : 'destino desconocido';

        return [
            'message' => 'Se ha retirado automáticamente una solicitud de un pasajero en tu viaje con destino ' . $destination . ' debido a que se subió a otro viaje con igual destino.',
            'url' => 'trips/'.($trip ? $trip->id : ''),
            'extras' => [
                'id' => $trip ? $trip->id : null,
            ],
            'image' => 'https://carpoolear.com.ar/app/static/img/carpoolear_logo.png',
        ];
    }
}
