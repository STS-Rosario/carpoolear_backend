<?php

namespace STS\Notifications;

use  STS\Services\Notifications\BaseNotification;
use  STS\Services\Notifications\Channels\MailChannel;
use  STS\Services\Notifications\Channels\DatabaseChannel;
use  STS\Services\Notifications\Channels\PushChannel;

class PendingRateNotification extends BaseNotification
{
    protected $via = [DatabaseChannel::class, MailChannel::class]; 

    public function toEmail($user)
    {
        return [
            'title' => 'Cuentanos como te fue al viaje hacia ' . $this->getAttribute('trip')->to_town . "?",
            'email_view' => 'pending_rate',
            'url' =>  config('app.url') .  '/app/#Active/'. $user->getAttribute('hash'),
        ];
    }

    public function toString()
    {
        return 'Tiene calificaciones pendientes.';
    }
}