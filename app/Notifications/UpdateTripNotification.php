<?php

namespace STS\Notifications;

use  STS\Services\Notifications\BaseNotification;
use  STS\Services\Notifications\Channels\MailChannel;
use  STS\Services\Notifications\Channels\PushChannel;
use  STS\Services\Notifications\Channels\DatabaseChannel;
use  STS\Services\Notifications\Channels\FacebookChannel;

class UpdateTripNotification extends BaseNotification
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
            'title' => $this->getAttribute('from')->name.' ha cambiado las condiciones de su viaje.',
            'email_view' => 'update_trip',
            'url' => config('app.url').'/app/trips/'.$this->getAttribute('trip')->id,
            'name_app' => config('carpoolear.name_app'),
            'domain' => config('app.url')
        ];
    }

    public function toString()
    {
        return $this->getAttribute('from')->name.' ha cambiado las condiciones de su viaje.';
    }

    public function getExtras()
    {
        $trip = $this->getAttribute('trip');

        return [
            'type' => 'trip',
            'trip_id' => isset($trip) && is_object($trip) ? $trip->id : 0,
        ];
    }

    public function toPush($user, $device)
    {
        $trip = $this->getAttribute('trip');

        return [
            'message' => $this->getAttribute('from')->name.' ha cambiado las condiciones de su viaje.',
            'url' => 'trips/'.$trip->id,
            'extras' => [
                'id' => $trip->id,
            ],
            'image' => 'https://carpoolear.com.ar/app/static/img/carpoolear_logo.png',
        ];
    }
}
