<?php

namespace STS\Notifications;

use  STS\Services\Notifications\BaseNotification;
use  STS\Services\Notifications\Channels\MailChannel;
use  STS\Services\Notifications\Channels\PushChannel;
use  STS\Services\Notifications\Channels\DatabaseChannel;

class HourLeftNotification extends BaseNotification
{
    protected $via = [DatabaseChannel::class, MailChannel::class, PushChannel::class];

    public function toEmail($user)
    {
        return [
            'title' => 'Recordatorio de viaje hacia'.$this->getAttribute('trip')->to_town,
            'email_view' => 'hour_left',
        ];
    }

    public function toString()
    {
        return 'Falta poco más de una hora para le viaje hacia '.$this->getAttribute('trip')->to_town;
    }

    public function getExtras()
    {
        return [
            'type' => 'trip',
            'trip_id' => $this->getAttribute('trip')->id,
        ];
    }

    public function toPush($user, $device)
    {
        $trip = $this->getAttribute('trip');

        return [
            'message' => 'Recuerada que en poco más de una hora viajas hacia '.$trip->to_town,
            'url' => 'trip',
            'extras' => [
                'id' => $trip->id,
            ],
        ];
    }
}
