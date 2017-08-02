<?php

namespace STS\Notifications;

use  STS\Services\Notifications\BaseNotification;
use  STS\Services\Notifications\Channels\MailChannel;
use  STS\Services\Notifications\Channels\PushChannel;
use  STS\Services\Notifications\Channels\DatabaseChannel;
use  STS\Services\Notifications\Channels\FacebookChannel;

class RequestRemainderNotification extends BaseNotification
{
    protected $via = [DatabaseChannel::class, MailChannel::class, PushChannel::class, FacebookChannel::class];

    public function toEmail($user)
    {
        return [
            'title' => 'Tienes solicitudes pendientes de contestar',
            'email_view' => 'request_remainder',
            'url' =>  config('app.url').'/app/profile/me#0',
        ];
    }

    public function toString()
    {
        return 'Tienes solicitudes pendientes de contestar.';
    }

    public function getExtras()
    {
        return [
            'type' => 'my-trips',
            'trip_id' => $this->getAttribute('trip')->id,
        ];
    }

    public function toPush($user, $device)
    {
        $trip = $this->getAttribute('trip');

        return [
            'message' => 'Tienes solicitudes pendientes de contestar.',
            'url' => 'my-trips',
            'extras' => [
                'id' => $trip->id,
            ],
        ];
    }
}
