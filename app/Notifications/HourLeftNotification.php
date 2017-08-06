<?php

namespace STS\Notifications;

use  STS\Services\Notifications\BaseNotification;
use  STS\Services\Notifications\Channels\MailChannel;
use  STS\Services\Notifications\Channels\PushChannel;
use  STS\Services\Notifications\Channels\DatabaseChannel;
use  STS\Services\Notifications\Channels\FacebookChannel;

class HourLeftNotification extends BaseNotification
{
    protected $via = [DatabaseChannel::class, MailChannel::class, PushChannel::class, FacebookChannel::class];

    public function toEmail($user)
    {
        return [
            'title' => 'Recordatorio de viaje hacia '.$this->getAttribute('trip')->to_town,
            'email_view' => 'hour_left',
            'url' => config('app.url').'/app/trips/'.$this->getAttribute('trip')->id,
        ];
    }

    public function toString()
    {
        return 'Recuerda que en poco más de una hora viajas hacia '.$this->getAttribute('trip')->to_town;
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
            'message' => 'Recuerda que en poco más de una hora viajas hacia '.$trip->to_town,
            'url' => 'trip',
            'extras' => [
                'id' => $trip->id,
            ],
        ];
    }
}
