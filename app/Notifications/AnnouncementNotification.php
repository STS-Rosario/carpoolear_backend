<?php

namespace STS\Notifications;

use STS\Services\Notifications\BaseNotification;
use STS\Services\Notifications\Channels\PushChannel;
use STS\Services\Notifications\Channels\DatabaseChannel;

class AnnouncementNotification extends BaseNotification
{
    protected $via = [
        DatabaseChannel::class,
        PushChannel::class,
        // MailChannel::class,  // Uncomment if you want email notifications
    ];

    public function toEmail($user)
    {
        $title = $this->getAttribute('title', 'Anuncio de Carpoolear');
        $message = $this->getAttribute('message');
        $externalUrl = $this->getAttribute('external_url');

        return [
            'title' => $title,
            'email_view' => 'announcement',
            'url' => $externalUrl ?: config('app.url'),
            'name_app' => config('carpoolear.name_app'),
            'domain' => config('app.url'),
            'message' => $message,
        ];
    }

    public function toString()
    {
        return $this->getAttribute('message', 'Nuevo anuncio de Carpoolear');
    }

    public function getExtras()
    {
        $externalUrl = $this->getAttribute('external_url');
        
        return [
            'type' => 'announcement',
            'external_url' => $externalUrl,
            'announcement_id' => $this->getAttribute('announcement_id'),
        ];
    }

    public function toPush($user, $device)
    {
        $title = $this->getAttribute('title', 'Carpoolear');
        $message = $this->getAttribute('message', 'Nuevo anuncio de Carpoolear');
        $externalUrl = $this->getAttribute('external_url');

        return [
            'message' => $message,
            'title' => $title,
            'url' => $externalUrl ?: 'app/home',
            'extras' => [
                'type' => 'announcement',
                'external_url' => $externalUrl,
                'announcement_id' => $this->getAttribute('announcement_id'),
            ],
            'image' => 'https://carpoolear.com.ar/app/static/img/carpoolear_logo.png',
        ];
    }
} 