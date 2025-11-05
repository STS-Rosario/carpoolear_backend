<?php

namespace STS\Notifications;

use  STS\Services\Notifications\BaseNotification;
use  STS\Services\Notifications\Channels\MailChannel;
use  STS\Services\Notifications\Channels\PushChannel;
use  STS\Services\Notifications\Channels\DatabaseChannel;
use  STS\Services\Notifications\Channels\FacebookChannel;

class NewMessagePushNotification extends BaseNotification
{
    protected $via = [
        PushChannel::class,
        DatabaseChannel::class, 
    ];

    public function toEmail($user)
    {
        $from = $this->getAttribute('from');
        $senderName = $from ? $from->name : 'Alguien';
        $messages = $this->getAttribute('messages');
        $conversationId = $messages ? $messages->conversation_id : '';

        return [
            'title' => $senderName.' te ha enviado un mensaje.',
            'email_view' => 'new_message',
            'url' => config('app.url') . '/app/conversations/'.$conversationId,
            'name_app' => config('carpoolear.name_app'),
            'domain' => config('app.url')
        ];
    }

    public function toString()
    {
        $from = $this->getAttribute('from');
        $senderName = $from ? $from->name : 'Alguien';
        return $senderName.' te ha enviado un mensaje.';
    }

    public function getExtras()
    {
        $messages = $this->getAttribute('messages');
        return [
            'type' => 'conversation',
            'conversation_id' => $messages ? $messages->conversation_id : null,
        ];
    }

    public function toPush($user, $device)
    {
        $message = $this->getAttribute('messages');
        $from = $this->getAttribute('from');
        $senderName = $from ? $from->name : 'Nuevo mensaje';
        $messageText = $message ? $message->text : '';
        $conversationId = $message ? $message->conversation_id : '';

        return [
            'message' => $senderName.' @ '.$messageText,
            'url' => '/conversations/'.$conversationId,
            'type' => 'conversation',
            'extras' => [
                'id' => $conversationId,
            ],
            'image' => 'https://carpoolear.com.ar/app/static/img/carpoolear_logo.png',
        ];
    }
}
