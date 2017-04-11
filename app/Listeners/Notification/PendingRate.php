<?php

namespace STS\Listeners\Notification;

use STS\Events\Rating\PendingRate as PendingEvent;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;

use STS\Notifications\PendingRateNotification;

class PendingRate
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
     * @param  PendingRate  $event
     * @return void
     */
    public function handle(PendingEvent $event)
    {
        $to = $event->to;
        $trip = $event->trip;
        $hash = $event->hash;

        $notification = new PendingRateNotification();
        $notification->setAttribute('trip', $trip);
        $notification->setAttribute('hash', $hash);
        //$notification->setAttribute('token', $to);
        $notification->notify($to);
        
    }
}
