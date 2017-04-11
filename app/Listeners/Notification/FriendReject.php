<?php

namespace STS\Listeners\Notification;

use STS\Events\Friend\Reject;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use STS\Notifications\FriendRejectNotification;

class FriendReject implements ShouldQueue
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
     * @param  Reject  $event
     * @return void
     */
    public function handle(Reject $event)
    {
        $from = $event->from;
        $to = $event->to;
        $notification = new FriendRejectNotification(); 
        $notification->setAttribute('from', $from); 
        $notification->notify($to);
    }
}
