<?php

namespace STS\Listeners\Notification;

use STS\Events\Rating\PendingRate as PendingEvent;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;

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
        $rate = $event->rate;
    }
}
