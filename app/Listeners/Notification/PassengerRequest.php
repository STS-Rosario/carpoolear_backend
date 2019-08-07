<?php

namespace STS\Listeners\Notification;

use STS\Events\Passenger\Request;
use Illuminate\Contracts\Queue\ShouldQueue;
use STS\Notifications\RequestPassengerNotification;

class PassengerRequest implements ShouldQueue
{
    protected $userRepository;

    protected $tripRepository;

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
     * @param  Request  $event
     * @return void
     */
    public function handle(Request $event)
    {
        $trip = $event->trip;
        $from = $event->from;
        $to = $event->to;
        if ($to) {
            $notification = new RequestPassengerNotification();
            $notification->setAttribute('trip', $trip);
            $notification->setAttribute('from', $from);
            //$notification->setAttribute('token', $to);
            $notification->notify($to);
        }
    }
}
