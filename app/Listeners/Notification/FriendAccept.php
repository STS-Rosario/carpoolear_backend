<?php

namespace STS\Listeners\Notification;

use STS\Events\Friend\Accept;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use STS\Notifications\FriendAcceptNotification;

class FriendAccept implements ShouldQueue
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
     * @param  Accept  $event
     * @return void
     */
    public function handle(Accept $event)
    {
        $from = $event->from;
        $to = $event->to;
        $notification = new FriendAcceptNotification(); 
        $notification->setAttribute('from', $from); 
        $notification->notify($to);
    }
}
