<?php

namespace STS\Listeners\Notification;

use Illuminate\Contracts\Queue\ShouldQueue;
use STS\Events\Trip\Alert\HourLeft;
use STS\Notifications\HourLeftNotification;

class TripHourLeft implements ShouldQueue
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
    public function handle(HourLeft $event)
    {
        $trip = $event->trip;
        $to = $event->to;
        if ($to) {
            $notification = new HourLeftNotification;
            $notification->setAttribute('trip', $trip);
            $notification->notify($to);
        }
    }
}
