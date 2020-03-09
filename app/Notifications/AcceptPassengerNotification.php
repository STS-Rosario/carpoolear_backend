<?php

namespace STS\Notifications;

use  STS\Services\Notifications\BaseNotification;
use  STS\Services\Notifications\Channels\MailChannel;
use  STS\Services\Notifications\Channels\PushChannel;
use  STS\Services\Notifications\Channels\DatabaseChannel;
use  STS\Services\Notifications\Channels\FacebookChannel;
use STS\Entities\Passenger;

class AcceptPassengerNotification extends BaseNotification
{
    protected $via = [DatabaseChannel::class, MailChannel::class, PushChannel::class, FacebookChannel::class];

    public function toEmail($user)
    {
        return [
            'title' => $this->getAttribute('from')->name.' ha aceptado tu solicitud.',
            'email_view' => 'passenger_email',
            'type' => 'accept',
            'reason_message' => 'ha aceptado',
            'url' => config('app.url').'/app/trips/'.$this->getAttribute('trip')->id,
            'name_app' => config('carpoolear.name_app'),
            'domain' => config('app.url')
        ];
    }

    public function toString()
    {
        return $this->getAttribute('from')->name.' ha aceptado tu solicitud.';
    }

    public function getExtras()
    {
        $to =  $this->getAttribute('token');
        if (is_object($to) && isset($to->id)) {
            $request = $this->getAttribute('trip')->passenger()->where('user_id', $to->id)->first();
            if (is_object($request) && $request->request_state == 4) {
                return [
                    'type' => 'my-trips',
                    'trip_id' => $this->getAttribute('trip')->id,
                ];
            }
        }
        return [
            'type' => 'trip',
            'trip_id' => $this->getAttribute('trip')->id,
        ];
    }

    public function toPush($user, $device)
    {
        $trip = $this->getAttribute('trip');

        return [
            'message' => $this->getAttribute('from')->name.' ha aceptado tu solicitud.',
            'url' => 'trips/'.$trip->id,
            'extras' => [
                'id' => $trip->id,
            ],
            'image' => 'https://carpoolear.com.ar/app/static/img/carpoolear_logo.png',
        ];
    }
}
