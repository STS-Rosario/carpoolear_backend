<?php

namespace STS\Listeners\Ratings;

use STS\Events\Trip\Delete as DeleteEvent;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;

class CreateRatingDeleteTrip implements ShouldQueue
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
     * @param  Delete  $event
     * @return void
     */
    public function handle(DeleteEvent $event)
    {
        $trip = $event->trip;
    }
}
