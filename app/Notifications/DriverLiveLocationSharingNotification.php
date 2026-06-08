<?php

namespace STS\Notifications;

use STS\Services\Notifications\BaseNotification;
use STS\Services\Notifications\Channels\DatabaseChannel;
use STS\Services\Notifications\Channels\MailChannel;
use STS\Services\Notifications\Channels\PushChannel;

class DriverLiveLocationSharingNotification extends BaseNotification
{
    public function __construct()
    {
        parent::__construct();
        $this->via = [
            DatabaseChannel::class,
            MailChannel::class,
            PushChannel::class,
        ];
    }

    public function toString()
    {
        $trip = $this->getAttribute('trip');
        $from = $this->getAttribute('from');
        $senderName = $from ? $from->name : __('notifications.someone');
        $destination = $trip ? $trip->to_town : __('notifications.destination_unknown');

        return __('notifications.driver_live_location.message', [
            'name' => $senderName,
            'destination' => $destination,
        ]);
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
            'url' => '/trips/'.($trip ? $trip->id : '').'/ubicacion',
            'extras' => [
                'type' => 'live_location',
                'id' => $trip ? $trip->id : null,
            ],
            'image' => 'https://carpoolear.com.ar/app/static/img/carpoolear_logo.png',
        ];
    }
}
