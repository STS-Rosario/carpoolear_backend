<?php

namespace STS\Notifications;

use STS\Services\Notifications\BaseNotification;
use STS\Services\Notifications\Channels\DatabaseChannel;
use STS\Services\Notifications\Channels\PushChannel;

class SupportTicketAutoClosedNotification extends BaseNotification
{
    protected $via = [
        DatabaseChannel::class,
        PushChannel::class,
    ];

    public function toString()
    {
        return 'Cerramos tu ticket por falta de respuesta';
    }

    public function getExtras()
    {
        $ticket = $this->getAttribute('ticket');

        return [
            'type' => 'ticket',
            'ticket_id' => $ticket ? $ticket->id : null,
        ];
    }

    public function toPush($user, $device)
    {
        $ticket = $this->getAttribute('ticket');

        return [
            'message' => 'Cerramos tu ticket por falta de respuesta',
            'url' => '/soporte/'.($ticket ? $ticket->id : ''),
            'type' => 'ticket',
            'extras' => [
                'id' => $ticket ? $ticket->id : null,
            ],
            'image' => 'https://carpoolear.com.ar/app/static/img/carpoolear_logo.png',
        ];
    }
}
