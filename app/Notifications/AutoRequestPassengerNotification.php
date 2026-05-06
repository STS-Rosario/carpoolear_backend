<?php

namespace STS\Notifications;

use STS\Services\Notifications\BaseNotification;
use STS\Services\Notifications\Channels\DatabaseChannel;
use STS\Services\Notifications\Channels\MailChannel;
use STS\Services\Notifications\Channels\PushChannel;

class AutoRequestPassengerNotification extends BaseNotification
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

    public function toEmail($user)
    {
        $trip = $this->getAttribute('trip');
        $from = $this->getAttribute('from');
        $senderName = $from ? $from->name : __('notifications.someone');

        return [
            'title' => __('notifications.auto_request_passenger.title', ['name' => $senderName]),
            'email_view' => 'auto_request_passenger',
            'url' => config('app.url').'/app/trips/'.($trip ? $trip->id : ''),
            'name_app' => config('carpoolear.name_app'),
            'domain' => config('app.url'),
        ];
    }

    public function toString()
    {
        $from = $this->getAttribute('from');
        $senderName = $from ? $from->name : __('notifications.someone');

        return __('notifications.auto_request_passenger.message', ['name' => $senderName]);
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
        $senderName = $from ? $from->name : __('notifications.someone');

        return [
            'message' => __('notifications.auto_request_passenger.message', ['name' => $senderName]),
            'url' => '/trips/'.($trip ? $trip->id : ''),
            'extras' => [
                'id' => $trip ? $trip->id : null,
            ],
            'image' => 'https://carpoolear.com.ar/app/static/img/carpoolear_logo.png',
        ];
    }
}
