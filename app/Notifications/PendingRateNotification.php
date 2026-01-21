<?php

namespace STS\Notifications;

use  STS\Services\Notifications\BaseNotification;
use  STS\Services\Notifications\Channels\MailChannel;
use  STS\Services\Notifications\Channels\PushChannel;
use  STS\Services\Notifications\Channels\DatabaseChannel;
use  STS\Services\Notifications\Channels\FacebookChannel;

class PendingRateNotification extends BaseNotification
{
    protected $via = [
        DatabaseChannel::class, 
        MailChannel::class, 
        PushChannel::class, 
        // FacebookChannel::class
    ];
    
    public function toEmail($user)
    {
        $trip = $this->getAttribute('trip');
        $destination = $trip ? $trip->to_town : __('notifications.destination_unknown');

        return [
            'title' => __('notifications.pending_rate.title', ['destination' => $destination]),
            'email_view' => 'pending_rate',
            'url' => config('app.url').'/app/profile/me#0',
            'name_app' => config('carpoolear.name_app'),
            'domain' => config('app.url')
        ];
    }

    public function toString()
    {
        return __('notifications.pending_rate.message');
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
            'message' => __('notifications.pending_rate.message'),
            'url' => '/my-trips',
            'image' => 'https://carpoolear.com.ar/app/static/img/carpoolear_logo.png',
        ];
    }
}
