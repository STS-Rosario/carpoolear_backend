<?php

namespace STS\Notifications;

use  STS\Services\Notifications\BaseNotification;
use  STS\Services\Notifications\Channels\MailChannel;
use  STS\Services\Notifications\Channels\PushChannel;
use  STS\Services\Notifications\Channels\DatabaseChannel;

class RequestNotAnswerNotification extends BaseNotification
{
    protected $via = [DatabaseChannel::class, MailChannel::class, PushChannel::class];

    public function toEmail($user)
    {
        return [
            'title' => 'Solicitudes pendientes sin contestar',
            'email_view' => 'request_not_answer',
            'url' =>  config('app.url').'/profile/me#0',
        ];
    }

    public function toString()
    {
        return 'Te recordamos que aún tienes solicitudes pendientes por contestar.';
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
            'message' => 'Te recordamos que aún tienes solicitudes pendientes por contestar.',
            'url' => 'my-trips',
            'extras' => [
                'id' => $trip->id,
            ],
        ];
    }
}
