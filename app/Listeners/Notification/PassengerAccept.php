<?php

namespace STS\Listeners\Notification;

use Illuminate\Contracts\Queue\ShouldQueue;
use STS\Events\Passenger\Accept;
use STS\Notifications\AcceptPassengerNotification;

class PassengerAccept implements ShouldQueue
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
     * @return void
     */
    public function handle(Accept $event)
    {
        $trip = $event->trip;
        $from = $event->from;
        $to = $event->to;
        if ($to) {
            $notification = new AcceptPassengerNotification;
            $notification->setAttribute('trip', $trip);
            $notification->setAttribute('from', $from);
            $notification->setAttribute('token', $to);
            $notification->notify($to);
        }
    }
}
