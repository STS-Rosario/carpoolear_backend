<?php

namespace STS\Notifications;

use  STS\Services\Notifications\BaseNotification;
use  STS\Services\Notifications\Channels\MailChannel;
use  STS\Services\Notifications\Channels\PushChannel;
use  STS\Services\Notifications\Channels\DatabaseChannel;
use  STS\Services\Notifications\Channels\FacebookChannel;

class SubscriptionMatchNotification extends BaseNotification
{
    // , MailChannel::class

    protected $via = [
        DatabaseChannel::class, 
        MailChannel::class, 
        PushChannel::class,
    ];

    public function toEmail($user)
    {
        $trip = $this->getAttribute('trip');

        return [
            'title' => 'Hemos encontrado un viaje que coincide con tu búsqueda',
            'email_view' => 'subscription_match',
            'url' => config('app.url').'/app/trips/'.($trip ? $trip->id : ''),
            'name_app' => config('carpoolear.name_app'),
            'domain' => config('app.url')
        ];
    }

    public function toString()
    {
        return 'Hemos encontrado un viaje que coincide con tu búsqueda.';
    }

    public function getExtras()
    {
        return [
            'type' => 'subscription',
        ];
    }

    public function toPush($user, $device)
    {
        $trip = $this->getAttribute('trip');

        return [
            'message' => 'Hemos encontrado un viaje que coincide con tu búsqueda.',
            'url' => 'trips/'.($trip ? $trip->id : ''),
            'extras' => [
                'id' => $trip ? $trip->id : null,
            ],
            'image' => 'https://carpoolear.com.ar/app/static/img/carpoolear_logo.png',
        ];
    }
}
