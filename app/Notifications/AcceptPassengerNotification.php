<?php

namespace STS\Notifications;

use  STS\Services\Notifications\BaseNotification;
use  STS\Services\Notifications\Channels\MailChannel;
use  STS\Services\Notifications\Channels\PushChannel;
use  STS\Services\Notifications\Channels\DatabaseChannel;
use  STS\Services\Notifications\Channels\FacebookChannel;
use STS\Models\Passenger;

class AcceptPassengerNotification extends BaseNotification
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
        $senderName = $from ? $from->name : __('notifications.someone');
        $tripDate = $trip ? $trip->trip_date : __('notifications.date_not_available');

        return [
            'title' => __('notifications.accept_passenger.title', ['name' => $senderName]),
            'email_view' => 'accept_passenger',
            'url' => config('app.url').'/app/trips/'.($trip ? $trip->id : ''),
            'name_app' => config('carpoolear.name_app'),
            'domain' => config('app.url')
        ];
    }

    public function toString()
    {
        $from = $this->getAttribute('from');
        $senderName = $from ? $from->name : __('notifications.someone');
        return __('notifications.accept_passenger.message', ['name' => $senderName]);
    }

    public function getExtras()
    {
        $trip = $this->getAttribute('trip');
        $to =  $this->getAttribute('token');
        if (is_object($to) && isset($to->id)) {
            $request = $this->getAttribute('trip')->passenger()->where('user_id', $to->id)->first();
            if (is_object($request) && $request->request_state == 4) {
                return [
                    'type' => 'my-trips',
                    'trip_id' => isset($trip) && is_object($trip) ? $trip->id : 0,
                ];
            }
        }
        return [
            'type' => 'trip',
            'trip_id' => $trip ? $trip->id : null,
        ];
    }

    public function toPush($user, $device)
    {
        $trip = $this->getAttribute('trip');
        $from = $this->getAttribute('from');
        $senderName = $from ? $from->name : __('notifications.someone');

        return [
            'message' => __('notifications.accept_passenger.message', ['name' => $senderName]),
            'url' => '/trips/'.($trip ? $trip->id : ''),
            'extras' => [
                'id' => $trip ? $trip->id : null,
            ],
            'image' => 'https://carpoolear.com.ar/app/static/img/carpoolear_logo.png',
        ];
    }
}
