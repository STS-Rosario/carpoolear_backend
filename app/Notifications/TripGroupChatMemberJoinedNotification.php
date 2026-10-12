<?php

namespace STS\Notifications;

use STS\Services\Notifications\BaseNotification;
use STS\Services\Notifications\Channels\DatabaseChannel;
use STS\Services\Notifications\Channels\PushChannel;

class TripGroupChatMemberJoinedNotification extends BaseNotification
{
    public function __construct()
    {
        parent::__construct();
        $this->via = [
            DatabaseChannel::class,
            PushChannel::class,
        ];
    }

    public function toString()
    {
        $user = $this->getAttribute('user');

        return __('notifications.group_chat_user_joined', ['name' => $user ? $user->name : __('notifications.someone')]);
    }

    public function getExtras()
    {
        $conversation = $this->getAttribute('conversation');

        return [
            'type' => 'conversation',
            'conversation_id' => $conversation ? $conversation->id : null,
        ];
    }

    public function toPush($user, $device)
    {
        $conversation = $this->getAttribute('conversation');
        $conversationId = $conversation ? $conversation->id : '';

        return [
            'message' => $this->toString(),
            'url' => '/conversations/'.$conversationId,
            'extras' => [
                'id' => $conversationId,
            ],
            'image' => 'https://carpoolear.com.ar/app/static/img/carpoolear_logo.png',
        ];
    }
}