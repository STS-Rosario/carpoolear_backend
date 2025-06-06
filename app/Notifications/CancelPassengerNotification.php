<?php

namespace STS\Notifications;

use  STS\Services\Notifications\BaseNotification;
use  STS\Services\Notifications\Channels\MailChannel;
use  STS\Services\Notifications\Channels\PushChannel;
use  STS\Services\Notifications\Channels\DatabaseChannel;
use  STS\Services\Notifications\Channels\FacebookChannel;

class CancelPassengerNotification extends BaseNotification
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
        $from = $this->getAttribute('from');
        $isDriver = $this->getAttribute('is_driver');
        $senderName = $from ? $from->name : 'Alguien';
        $title = $isDriver ? $senderName.' te ha bajado del viaje' : $senderName.' se ha bajado del viaje';

        return [
            'title' => $title,
            'email_view' => 'cancel_passenger',
            'url' => config('app.url').'/app/trips/'.($trip ? $trip->id : ''),
            'name_app' => config('carpoolear.name_app'),
            'domain' => config('app.url')
        ];
    }

    public function toString()
    {
        $from = $this->getAttribute('from');
        $isDriver = $this->getAttribute('is_driver');
        $senderName = $from ? $from->name : 'Alguien';
        return $isDriver ? $senderName.' te ha bajado del viaje' : $senderName.' se ha bajado del viaje';
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
        $from = $this->getAttribute('from');
        $isDriver = $this->getAttribute('is_driver');
        $senderName = $from ? $from->name : 'Alguien';
        $message = $isDriver ? $senderName.' te ha bajado del viaje' : $senderName.' se ha bajado del viaje';

        return [
            'message' => $message,
            'url' => 'trips/'.($trip ? $trip->id : ''),
            'extras' => [
                'id' => $trip ? $trip->id : null,
            ],
            'image' => 'https://carpoolear.com.ar/app/static/img/carpoolear_logo.png',
        ];
    }
}
