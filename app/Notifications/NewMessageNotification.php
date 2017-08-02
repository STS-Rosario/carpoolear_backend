<?php

namespace STS\Notifications;

use  STS\Services\Notifications\BaseNotification;
use  STS\Services\Notifications\Channels\MailChannel;
use  STS\Services\Notifications\Channels\PushChannel;
use  STS\Services\Notifications\Channels\DatabaseChannel;
use  STS\Services\Notifications\Channels\FacebookChannel;

class NewMessageNotification extends BaseNotification
{
    protected $via = [DatabaseChannel::class, MailChannel::class, PushChannel::class, FacebookChannel::class];

    public function toEmail($user)
    {
        return [
            'title' => $this->getAttribute('from')->name.' te ha enviado un mensaje.',
            'email_view' => 'new_message',
            'url' => config('app.url').'/app/conversations/'.$this->getAttribute('messages')->conversation_id
        ];
    }

    public function toString()
    {
        return $this->getAttribute('from')->name.' te ha enviado un mensaje.';
    }

    public function getExtras()
    {
        return [
            'type' => 'conversation',
            'conversation_id' => $this->getAttribute('messages')->conversation_id,
        ];
    }

    public function toPush($user, $device)
    {
        $message = $this->getAttribute('messages');

        return [
            'message' => $this->getAttribute('from')->name.'@'.$message->text,
            'url' => 'conversation',
            'extras' => [
                'id' => $message->conversation_id,
            ],
        ];
    }
}
