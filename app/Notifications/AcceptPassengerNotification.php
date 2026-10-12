<?php

namespace STS\Notifications;

use STS\Models\Passenger;
use STS\Services\Notifications\BaseNotification;
use STS\Services\Notifications\Channels\DatabaseChannel;
use STS\Services\Notifications\Channels\MailChannel;
use STS\Services\Notifications\Channels\PushChannel;

class AcceptPassengerNotification extends BaseNotification
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
        $conversation = $this->getAttribute('conversation');
        $tripDate = $trip ? $trip->trip_date : __('notifications.date_not_available');
        $url = $conversation
            ? config('app.url').'/app/conversations/'.$conversation->id
            : config('app.url').'/app/trips/'.($trip ? $trip->id : '');

        return [
            'title' => __('notifications.accept_passenger.title', ['name' => $senderName]),
            'email_view' => 'accept_passenger',
            'url' => $url,
            'name_app' => config('carpoolear.name_app'),
            'domain' => config('app.url'),
        ];
    }

    public function toString()
    {
        $from = $this->getAttribute('from');
        $senderName = $from ? $from->name : __('notifications.someone');

        $message = __('notifications.accept_passenger.message', ['name' => $senderName]);
        if ($this->getAttribute('conversation')) {
            $message .= ' '.$this->groupChatCreatedMessage();
        }

        return $message;
    }

    public function getExtras()
    {
        $conversation = $this->getAttribute('conversation');
        if ($conversation) {
            return [
                'type' => 'conversation',
                'conversation_id' => (int) $conversation->id,
            ];
        }

        $trip = $this->getAttribute('trip');
        $to = $this->getAttribute('token');
        if (is_object($to) && isset($to->id)) {
            $request = $this->getAttribute('trip')->passenger()->where('user_id', $to->id)->first();
            if (is_object($request) && (int) $request->request_state === Passenger::STATE_WAITING_PAYMENT) {
                return [
                    'type' => 'my-trips',
                    'trip_id' => (isset($trip) && is_object($trip)) ? $trip->id : 0,
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
        $conversation = $this->getAttribute('conversation');
        $conversationId = $conversation ? $conversation->id : '';
        $url = $conversation ? '/conversations/'.$conversationId : '/trips/'.($trip ? $trip->id : '');

        return [
            'message' => $this->toString(),
            'url' => $url,
            'extras' => [
                'id' => $trip ? $trip->id : null,
            ],
            'image' => 'https://carpoolear.com.ar/app/static/img/carpoolear_logo.png',
        ];
    }

    private function groupChatCreatedMessage(): string
    {
        $trip = $this->getAttribute('trip');

        return __('notifications.accept_passenger.group_chat_created', [
            'destination' => $trip && $trip->to_town ? $trip->to_town : __('notifications.destination_unknown'),
            'day' => $this->tripDay($trip),
            'hour' => $this->tripHour($trip),
        ]);
    }

    private function tripDay($trip): string
    {
        return $trip && $trip->trip_date
            ? $trip->trip_date->format('d/m/Y')
            : __('notifications.date_not_available');
    }

    private function tripHour($trip): string
    {
        return $trip && $trip->trip_date
            ? $trip->trip_date->format('H:i')
            : __('notifications.date_not_available');
    }
}
