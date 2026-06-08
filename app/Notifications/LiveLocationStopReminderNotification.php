<?php

namespace STS\Notifications;

use STS\Services\Notifications\BaseNotification;
use STS\Services\Notifications\Channels\DatabaseChannel;
use STS\Services\Notifications\Channels\PushChannel;

class LiveLocationStopReminderNotification extends BaseNotification
{
    public function __construct()
    {
        parent::__construct();
        $this->via = [
            PushChannel::class,
            DatabaseChannel::class,
        ];
    }

    public function toString()
    {
        return 'Recordá detener la ubicación en tiempo real cuando desees';
    }

    public function getExtras()
    {
        $trip = $this->getAttribute('trip');

        return [
            'type' => 'live_location',
            'trip_id' => $trip ? $trip->id : null,
        ];
    }

    public function toPush($user, $device)
    {
        $trip = $this->getAttribute('trip');

        return [
            'message' => $this->toString(),
            'url' => '/trips/'.($trip ? $trip->id : '').'/live',
            'extras' => [
                'type' => 'live_location',
                'id' => $trip ? $trip->id : null,
            ],
            'image' => 'https://carpoolear.com.ar/app/static/img/carpoolear_logo.png',
        ];
    }
}
