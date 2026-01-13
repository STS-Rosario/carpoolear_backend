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
        $destination = $trip ? $trip->to_town : __('notifications.destination_unknown');

        return [
            'title' => __('notifications.auto_cancel_passenger_request.title', ['destination' => $destination]),
            'email_view' => 'auto_cancel_request',
            'url' => config('app.url').'/app/trips/'.($trip ? $trip->id : ''),
            'name_app' => config('carpoolear.name_app'),
            'domain' => config('app.url')
        ];
    }

    public function toString()
    {
        $trip = $this->getAttribute('trip');
        $destination = $trip ? $trip->to_town : __('notifications.destination_unknown');
        return __('notifications.auto_cancel_passenger_request.message', ['destination' => $destination]);
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
        $destination = $trip ? $trip->to_town : __('notifications.destination_unknown');

        return [
            'message' => __('notifications.auto_cancel_passenger_request.message', ['destination' => $destination]),
            'url' => '/trips/'.($trip ? $trip->id : ''),
            'extras' => [
                'id' => $trip ? $trip->id : null,
            ],
            'image' => 'https://carpoolear.com.ar/app/static/img/carpoolear_logo.png',
        ];
    }
}
