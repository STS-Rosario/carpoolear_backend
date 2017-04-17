<?php

namespace STS\Listeners\Notification;

use STS\Events\MessageSend as SendEvent;
use STS\Notifications\NewMessageNotification;

class MessageSend
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
     * @param  SendEvent  $event
     * @return void
     */
    public function handle(SendEvent $event)
    {
        $from = $event->from;
        $to = $event->to;
        $message = $event->message;
        $notification = new NewMessageNotification();
        $notification->setAttribute('from', $from);
        $notification->setAttribute('messages', $message);
        $notification->notify($to);
    }
}
