<?php

namespace STS\Listeners\Notification;

use STS\Events\Trip\Alert\RequestRemainder;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use STS\Notifications\RequestRemainderNotification;
class TripRequestRemainder implements ShouldQueue
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
     * @param  RequestRemainder  $event
     * @return void
     */
    public function handle(RequestRemainder $event)
    {
        $trip = $event->trip; 
        $to = $trip->user;
        if ($to) {
            $notification = new RequestRemainderNotification();
            $notification->setAttribute('trip', $trip); 
            $notification->notify($to);
        }
    }
}
