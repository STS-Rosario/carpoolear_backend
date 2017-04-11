<?php

namespace STS\Listeners\Notification;

use STS\Events\Friend\Cancel;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use STS\Notifications\FriendCancelNotification;

class FriendCancel implements ShouldQueue
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
     * @param  Cancel  $event
     * @return void
     */
    public function handle(Cancel $event)
    {
        $from = $event->from;
        $to = $event->to;
        $notification = new FriendCancelNotification(); 
        $notification->setAttribute('from', $from); 
        $notification->notify($to);
    }
}
