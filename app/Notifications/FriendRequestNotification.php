<?php

namespace STS\Notifications;

use  STS\Services\Notifications\BaseNotification;
use  STS\Services\Notifications\Channels\MailChannel;
use  STS\Services\Notifications\Channels\PushChannel;
use  STS\Services\Notifications\Channels\DatabaseChannel;

class FriendRequestNotification extends BaseNotification
{
    protected $via = [DatabaseChannel::class, MailChannel::class, PushChannel::class];

    public function toEmail($user)
    {
        return [
            'title' => 'Nueva solicitud de amistad',
            'email_view' => 'friends_request',
            'type' => 'request',
            'url' => 'url' => config('app.url').'/app/setting/friends'
        ];
    }

    public function toString()
    {
        return $this->getAttribute('from')->name.' te ha enviado una solicitud de amistad.';
    }

    public function getExtras()
    {
        return [
            'type' => 'friends',
        ];
    }

    public function toPush($user, $device)
    {
        $from = $this->getAttribute('from');

        return [
            'message' => $from->name.' te ha enviado una solicitud de amistad.',
            'url' => 'friend',
            'extras' => [
                'id' => $from->id,
            ],
        ];
    }
}
