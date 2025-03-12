<?php

namespace STS\Notifications;

use  STS\Services\Notifications\BaseNotification;
use  STS\Services\Notifications\Channels\MailChannel;
use  STS\Services\Notifications\Channels\PushChannel;
use  STS\Services\Notifications\Channels\DatabaseChannel;
use  STS\Services\Notifications\Channels\FacebookChannel;

class PendingRateNotification extends BaseNotification
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
            'title' => 'Contanos como te fue en el viaje hacia '.$destination.'?',
            'email_view' => 'pending_rate',
            'url' => config('app.url').'/app/profile/me#0',
            'name_app' => config('carpoolear.name_app'),
            'domain' => config('app.url')
        ];
    }

    public function toString()
    {
        return 'Tienes un viaje por calificar.';
    }

    public function getExtras()
    {
        return [
            'type' => 'my-trips',
        ];
    }

    public function toPush($user, $device)
    {
        return [
            'message' => 'Tienes un viaje por calificar.',
            'url' => 'my-trips',
            'image' => 'https://carpoolear.com.ar/app/static/img/carpoolear_logo.png',
        ];
    }
}
