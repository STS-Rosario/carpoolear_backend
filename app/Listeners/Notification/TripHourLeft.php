<?php

namespace STS\Listeners\Notification;

use STS\Events\Trip\Alert\HourLeft;
use Illuminate\Contracts\Queue\ShouldQueue;
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
     * @param  HourLeft  $event
     * @return void
     */
    public function handle(HourLeft $event)
    {
        $trip = $event->trip;
        $to = $event->to;
        if ($to) {
            $notification = new HourLeftNotification();
            $notification->setAttribute('trip', $trip);
            $notification->notify($to);
        }
    }
}
