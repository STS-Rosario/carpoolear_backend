<?php

namespace STS\Notifications;

use STS\Services\Notifications\BaseNotification;
use STS\Services\Notifications\Channels\DatabaseChannel;
use STS\Services\Notifications\Channels\MailChannel;
use STS\Services\Notifications\Channels\PushChannel;

class FriendCreatedTripNotification extends BaseNotification
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
        $driver = $this->getAttribute('driver');
        $driverName = $driver ? $driver->name : __('notifications.someone');
        $destination = $trip ? $trip->to_town : __('notifications.destination_unknown');
        $date = $this->tripDate($trip);
        $time = $this->tripTime($trip);

        return [
            'title' => __('notifications.friend_created_trip.title', [
                'name' => $driverName,
                'destination' => $destination,
            ]),
            'email_view' => 'subscription_match',
            'url' => config('app.url').'/app/trips/'.($trip ? $trip->id : ''),
            'name_app' => config('carpoolear.name_app'),
            'domain' => config('app.url'),
            'message' => __('notifications.friend_created_trip.message', [
                'name' => $driverName,
                'destination' => $destination,
                'date' => $date,
                'time' => $time,
            ]),
        ];
    }

    public function toString()
    {
        $trip = $this->getAttribute('trip');
        $driver = $this->getAttribute('driver');
        $driverName = $driver ? $driver->name : __('notifications.someone');
        $destination = $trip ? $trip->to_town : __('notifications.destination_unknown');

        return __('notifications.friend_created_trip.message', [
            'name' => $driverName,
            'destination' => $destination,
            'date' => $this->tripDate($trip),
            'time' => $this->tripTime($trip),
        ]);
    }

    public function getExtras()
    {
        $trip = $this->getAttribute('trip');
        $driver = $this->getAttribute('driver');

        return [
            'type' => 'friend_trip',
            'trip_id' => $trip ? $trip->id : null,
            'user_id' => $driver ? $driver->id : null,
        ];
    }

    public function toPush($user, $device)
    {
        $trip = $this->getAttribute('trip');
        $driver = $this->getAttribute('driver');
        $driverName = $driver ? $driver->name : __('notifications.someone');
        $destination = $trip ? $trip->to_town : __('notifications.destination_unknown');

        return [
            'message' => __('notifications.friend_created_trip.message', [
                'name' => $driverName,
                'destination' => $destination,
                'date' => $this->tripDate($trip),
                'time' => $this->tripTime($trip),
            ]),
            'url' => '/trips/'.($trip ? $trip->id : ''),
            'extras' => [
                'id' => $trip ? $trip->id : null,
            ],
            'image' => 'https://carpoolear.com.ar/app/static/img/carpoolear_logo.png',
        ];
    }

    private function tripDate($trip): string
    {
        if (! $trip || ! $trip->trip_date) {
            return __('notifications.date_not_available');
        }

        return $trip->trip_date->format('d/m/Y');
    }

    private function tripTime($trip): string
    {
        if (! $trip || ! $trip->trip_date) {
            return __('notifications.date_not_available');
        }

        return $trip->trip_date->format('H:i');
    }
}
