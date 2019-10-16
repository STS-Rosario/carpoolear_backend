<?php

namespace STS\Listeners\Notification;

use STS\Events\MessageSend as SendEvent;
use Illuminate\Contracts\Queue\ShouldQueue;
use STS\Notifications\NewMessageNotification;

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
        try {
            $notification->notify($to);
        } catch (\Exception $e) {
            \Log::info('Error on sending notification');
            \Log::info($e);
        }
    }
}
