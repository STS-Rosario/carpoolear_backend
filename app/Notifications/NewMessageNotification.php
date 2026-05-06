<?php

namespace STS\Notifications;

use STS\Services\Notifications\BaseNotification;
use STS\Services\Notifications\Channels\DatabaseChannel;
use STS\Services\Notifications\Channels\MailChannel;
use STS\Services\Notifications\Channels\PushChannel;

class NewMessageNotification extends BaseNotification
{
    public function __construct()
    {
        parent::__construct();
        $this->via = [
            DatabaseChannel::class,
            MailChannel::class,
            PushChannel::class,
        ];
    }

    public function toEmail($user)
    {
        $from = $this->getAttribute('from');
        $message = $this->getAttribute('messages');
        $senderName = $from ? $from->name : __('notifications.someone');

        return [
            'title' => __('notifications.new_message.title', ['name' => $senderName]),
            'email_view' => 'new_message',
            'url' => config('app.url').'/app/conversations/'.($message ? $message->conversation_id : ''),
            'name_app' => config('carpoolear.name_app'),
            'domain' => config('app.url'),
        ];
    }

    public function toString()
    {
        $from = $this->getAttribute('from');
        $senderName = $from ? $from->name : __('notifications.someone');

        return __('notifications.new_message.title', ['name' => $senderName]);
    }

    public function getExtras()
    {
        $message = $this->getAttribute('messages');

        return [
            'type' => 'conversation',
            'conversation_id' => $message ? $message->conversation_id : null,
        ];
    }

    public function toPush($user, $device)
    {
        $message = $this->getAttribute('messages');
        $from = $this->getAttribute('from');
        $senderName = $from ? $from->name : __('notifications.someone');

        return [
            'message' => __('notifications.new_message.message', ['name' => $senderName]),
            'url' => '/conversations/'.($message ? $message->conversation_id : ''),
            'type' => 'conversation',
            'extras' => [
                'id' => $message ? $message->conversation_id : null,
            ],
            'image' => 'https://carpoolear.com.ar/app/static/img/carpoolear_logo.png',
        ];
    }
}
