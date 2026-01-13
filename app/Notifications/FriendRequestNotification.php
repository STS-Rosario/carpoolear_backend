<?php

namespace STS\Notifications;

use  STS\Services\Notifications\BaseNotification;
use  STS\Services\Notifications\Channels\MailChannel;
use  STS\Services\Notifications\Channels\PushChannel;
use  STS\Services\Notifications\Channels\DatabaseChannel;
use  STS\Services\Notifications\Channels\FacebookChannel;

class FriendRequestNotification extends BaseNotification
{
    protected $via = [
        DatabaseChannel::class, 
        MailChannel::class, 
        PushChannel::class, 
        // FacebookChannel::class
    ];
    
    public function toEmail($user)
    {
        return [
            'title' => __('notifications.friend_request.title'),
            'email_view' => 'friends_request',
            'type' => 'request',
            'url' => config('app.url').'/app/setting/friends',
            'name_app' => config('carpoolear.name_app'),
            'domain' => config('app.url')
        ];
    }

    public function toString()
    {
        $from = $this->getAttribute('from');
        $senderName = $from ? $from->name : __('notifications.someone');
        return __('notifications.friend_request.message', ['name' => $senderName]);
    }

    public function getExtras()
    {
        $from = $this->getAttribute('from');
        return [
            'type' => 'friends',
            'user_id' => $from ? $from->id : null,
        ];
    }

    public function toPush($user, $device)
    {
        $from = $this->getAttribute('from');
        $senderName = $from ? $from->name : __('notifications.someone');

        return [
            'message' => __('notifications.friend_request.message', ['name' => $senderName]),
            'url' => '/setting/friends',
            'extras' => [
                'id' => $from ? $from->id : null,
            ],
            'image' => 'https://carpoolear.com.ar/app/static/img/carpoolear_logo.png',
        ];
    }
}
