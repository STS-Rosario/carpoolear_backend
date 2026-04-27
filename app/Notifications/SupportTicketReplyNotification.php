<?php

namespace STS\Notifications;

use STS\Services\Notifications\BaseNotification;
use STS\Services\Notifications\Channels\DatabaseChannel;
use STS\Services\Notifications\Channels\PushChannel;

class SupportTicketReplyNotification extends BaseNotification
{
    protected $via = [
        DatabaseChannel::class,
        PushChannel::class,
    ];

    public function toString()
    {
        return 'Tenes una nueva respuesta de Carpoolear';
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
            'message' => 'Tenes una nueva respuesta de Carpoolear',
            'url' => '/tickets/'.($ticket ? $ticket->id : ''),
            'type' => 'ticket',
            'extras' => [
                'id' => $ticket ? $ticket->id : null,
            ],
            'image' => 'https://carpoolear.com.ar/app/static/img/carpoolear_logo.png',
        ];
    }
}
