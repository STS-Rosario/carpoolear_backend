<?php

namespace STS\Notifications;

use STS\Services\Notifications\BaseNotification;
use STS\Services\Notifications\Channels\DatabaseChannel;
use STS\Services\Notifications\Channels\PushChannel;

class TripGroupChatCreatedNotification extends BaseNotification
{
    public function __construct()
    {
        parent::__construct();
        $this->via = [
            DatabaseChannel::class,
            PushChannel::class,
        ];
    }

    public function toString()
    {
        $trip = $this->getAttribute('trip');

        return __('notifications.group_chat_created.message', [
            'destination' => $trip ? $trip->to_town : __('notifications.destination_unknown'),
            'day' => $this->tripDay($trip),
            'hour' => $this->tripHour($trip),
        ]);
    }

    public function getExtras()
    {
        $conversation = $this->getAttribute('conversation');

        return [
            'type' => 'conversation',
            'conversation_id' => $conversation ? $conversation->id : null,
        ];
    }

    public function toPush($user, $device)
    {
        $conversation = $this->getAttribute('conversation');
        $conversationId = $conversation ? $conversation->id : '';

        return [
            'message' => $this->toString(),
            'url' => '/conversations/'.$conversationId,
            'type' => 'conversation',
            'extras' => [
                'id' => $conversationId,
            ],
            'image' => 'https://carpoolear.com.ar/app/static/img/carpoolear_logo.png',
        ];
    }

    private function tripDay($trip): string
    {
        if (! $trip || ! $trip->trip_date) {
            return __('notifications.date_not_available');
        }

        return $trip->trip_date->format('d/m/Y');
    }

    private function tripHour($trip): string
    {
        if (! $trip || ! $trip->trip_date) {
            return __('notifications.date_not_available');
        }

        return $trip->trip_date->format('H:i');
    }
}
