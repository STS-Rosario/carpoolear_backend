<?php

namespace STS\Listeners\Notification;

use Illuminate\Contracts\Queue\ShouldQueue;
use STS\Events\Friend\Request;
use STS\Notifications\FriendRequestNotification;

class FriendRequest implements ShouldQueue
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
     *
     * @return void
     */
    public function handle(Request $event)
    {
        $from = $event->from;
        $to = $event->to;
        $notification = new FriendRequestNotification;
        $notification->setAttribute('from', $from);
        $notification->notify($to);
    }
}
