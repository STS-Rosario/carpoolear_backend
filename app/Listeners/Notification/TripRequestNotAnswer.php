<?php

namespace STS\Listeners\Notification;

use STS\Events\Trip\Alert\RequestNotAnswer;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use STS\Notifications\RequestNotAnswerNotification;

class TripRequestNotAnswer implements ShouldQueue
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
     * @param  RequestNotAnswer  $event
     * @return void
     */
    public function handle(RequestNotAnswer $event)
    {
        $trip = $event->trip; 
        $to = $event->to;
        if ($to) {
            $notification = new RequestNotAnswerNotification();
            $notification->setAttribute('trip', $trip); 
            $notification->notify($to);
        }
    }
}
