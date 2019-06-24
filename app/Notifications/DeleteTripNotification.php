<?php

namespace STS\Notifications;

use  STS\Services\Notifications\BaseNotification;
use  STS\Services\Notifications\Channels\MailChannel;
use  STS\Services\Notifications\Channels\PushChannel;
use  STS\Services\Notifications\Channels\DatabaseChannel;
use  STS\Services\Notifications\Channels\FacebookChannel;

class DeleteTripNotification extends BaseNotification
{
    protected $via = [DatabaseChannel::class, MailChannel::class, PushChannel::class, FacebookChannel::class];

    public function toEmail($user)
    {
        return [
            'title' => $this->getAttribute('from')->name.' ha eliminado su viaje. ',
            'email_view' => 'pending_rate_delete',
            'url' =>  config('app.url').'/app/profile/me#0',
        ];
    }

    public function toString()
    {
        return $this->getAttribute('from')->name.' ha eliminado su viaje. Puedes calificarlo. ';
    }

    public function getExtras()
    {
        return [
            'type' => 'my-trips',
        ];
    }

    public function toPush($user, $device)
    {
        return [
            'message' => $this->getAttribute('from')->name.' ha eliminado su viaje. Puedes calificarlo. ',
            'url' => 'rates',
            'image' => 'https://carpoolear.com.ar/app/static/img/carpoolear_logo.png',
        ];
    }
}
