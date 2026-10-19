<?php

namespace STS\Listeners\Notification;

use Illuminate\Contracts\Queue\ShouldQueue;
use STS\Events\Friend\Reject;
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
     * @return void
     */
    public function handle(Reject $event)
    {
        $from = $event->from;
        $to = $event->to;
        $notification = new FriendRejectNotification;
        $notification->setAttribute('from', $from);
        $notification->notify($to);
    }
}
