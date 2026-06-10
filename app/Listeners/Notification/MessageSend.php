<?php

namespace STS\Listeners\Notification;

use Illuminate\Contracts\Queue\ShouldQueue;
use STS\Events\MessageSend as SendEvent;
use STS\Notifications\NewMessagePushNotification;

class MessageSend implements ShouldQueue
{
    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     *
     * @return void
     */
    public function handle(SendEvent $event)
    {
        $from = $event->from;
        $to = $event->to;
        $message = $event->message;
        $conversation = $message->conversation ?? null;
        if (! $conversation && isset($message->conversation_id)) {
            $conversation = \STS\Models\Conversation::find($message->conversation_id);
        }
        if ($conversation && ! $conversation->notificationsEnabled($to)) {
            return;
        }
        $notification = new NewMessagePushNotification;
        $notification->setAttribute('from', $from);
        $notification->setAttribute('messages', $message);
        try {
            $notification->notify($to);
        } catch (\Exception $e) {
            \Log::warning('Error on sending notification', ['message' => $e->getMessage()]);
        }
    }
}
