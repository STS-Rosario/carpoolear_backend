<?php

namespace STS\Notifications;

use  STS\Services\Notifications\BaseNotification;
use  STS\Services\Notifications\Channels\MailChannel;
use  STS\Services\Notifications\Channels\PushChannel;
use  STS\Services\Notifications\Channels\DatabaseChannel;
use  STS\Services\Notifications\Channels\FacebookChannel;

class PendingRateNotification extends BaseNotification
{
    protected $via = [DatabaseChannel::class, MailChannel::class, PushChannel::class, FacebookChannel::class];

    public function toEmail($user)
    {
        return [
            'title' => 'Contanos como te fue en el viaje hacia '.$this->getAttribute('trip')->to_town.'?',
            'email_view' => 'pending_rate',
            'url' =>  config('app.url').'/app/profile/me#0'
        ];
    }

    public function toString()
    {
        return 'Tienes un viaje por calificar.';
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
            'message' => 'Tienes un viaje por calificar.',
            'url' => 'rates',
        ];
    }
}
