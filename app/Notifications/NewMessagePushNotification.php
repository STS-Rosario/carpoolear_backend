<?php

namespace STS\Notifications;

use STS\Models\Conversation;
use STS\Models\Trip;
use STS\Services\Notifications\BaseNotification;
use STS\Services\Notifications\Channels\DatabaseChannel;
use STS\Services\Notifications\Channels\PushChannel;

class NewMessagePushNotification extends BaseNotification
{
    protected $via = [
        PushChannel::class,
        DatabaseChannel::class,
    ];

    public function toEmail($user)
    {
        $from = $this->getAttribute('from');
        $senderName = $from ? $from->name : __('notifications.someone');
        $messages = $this->getAttribute('messages');
        $conversationId = $messages ? $messages->conversation_id : '';

        return [
            'title' => __('notifications.new_message.title', ['name' => $senderName]),
            'email_view' => 'new_message',
            'url' => config('app.url').'/app/conversations/'.$conversationId,
            'name_app' => config('carpoolear.name_app'),
            'domain' => config('app.url'),
        ];
    }

    public function toString()
    {
        $from = $this->getAttribute('from');
        $message = $this->getAttribute('messages');
        $senderName = $from ? $from->name : __('notifications.someone');

        return $this->buildNotificationText($message, $senderName, false);
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
        $senderName = $from ? $from->name : __('notifications.new_message.new_message');
        $conversationId = $message ? $message->conversation_id : '';

        return [
            'message' => $this->buildNotificationText($message, $senderName, true),
            'url' => '/conversations/'.$conversationId,
            'type' => 'conversation',
            'extras' => [
                'id' => $conversationId,
            ],
            'image' => 'https://carpoolear.com.ar/app/static/img/carpoolear_logo.png',
        ];
    }

    private function buildNotificationText($message, string $senderName, bool $includeMessageText): string
    {
        $messageText = $message ? $message->text : '';

        if ($message && $message->conversation_id) {
            $conversation = Conversation::find($message->conversation_id);
            if ($conversation
                && (int) $conversation->type === Conversation::TYPE_TRIP_CONVERSATION
                && $conversation->trip_id) {
                $trip = Trip::find($conversation->trip_id);
                if ($trip && $trip->trip_date) {
                    $tripTitle = __('notifications.group_chat_message.trip_title', [
                        'date' => $trip->trip_date->format('d/m/Y'),
                        'hour' => $trip->trip_date->format('H:i'),
                    ]);

                    if ($includeMessageText) {
                        return __('notifications.group_chat_message.title', [
                            'trip' => $tripTitle,
                            'name' => $senderName,
                            'text' => $messageText,
                        ]);
                    }

                    return $tripTitle.': '.__('notifications.new_message.title', ['name' => $senderName]);
                }
            }
        }

        if ($includeMessageText) {
            return $senderName.' @ '.$messageText;
        }

        return __('notifications.new_message.title', ['name' => $senderName]);
    }
}
