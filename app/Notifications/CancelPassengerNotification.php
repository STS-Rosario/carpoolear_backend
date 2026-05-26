<?php

namespace STS\Notifications;

use STS\Services\Notifications\BaseNotification;
use STS\Services\Notifications\Channels\DatabaseChannel;
use STS\Services\Notifications\Channels\FacebookChannel;
use STS\Services\Notifications\Channels\MailChannel;
use STS\Services\Notifications\Channels\PushChannel;

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

        return [
            'title' => $this->message(),
            'email_view' => 'cancel_passenger',
            'url' => config('app.url').'/app/trips/'.($trip ? $trip->id : ''),
            'name_app' => config('carpoolear.name_app'),
            'domain' => config('app.url'),
        ];
    }

    public function toString()
    {
        return $this->message();
    }

    public function getExtras()
    {
        $trip = $this->getAttribute('trip');
        $isDriver = $this->getAttribute('is_driver');

        return [
            'type' => $isDriver ? 'trip' : 'my-trips',
            'trip_id' => $trip ? $trip->id : null,
        ];
    }

    public function toPush($user, $device)
    {
        $trip = $this->getAttribute('trip');
        $isDriver = $this->getAttribute('is_driver');

        return [
            'message' => $this->message(),
            'url' => $isDriver ? '/trips/'.($trip ? $trip->id : '') : '/my-trips',
            'extras' => [
                'id' => $trip ? $trip->id : null,
            ],
            'image' => 'https://carpoolear.com.ar/app/static/img/carpoolear_logo.png',
        ];
    }

    private function message(): string
    {
        $from = $this->getAttribute('from');
        $senderName = $from ? $from->name : __('notifications.someone');

        return $this->getAttribute('is_driver')
            ? __('notifications.cancel_passenger.driver_removed', ['name' => $senderName])
            : __('notifications.cancel_passenger.passenger_left', ['name' => $senderName]);
    }
}
