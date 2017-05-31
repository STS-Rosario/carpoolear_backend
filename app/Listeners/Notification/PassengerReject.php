<?php

namespace STS\Listeners\Notification;

use STS\Events\Passenger\Reject;
use Illuminate\Contracts\Queue\ShouldQueue;
use STS\Notifications\RejectPassengerNotification;

class PassengerReject implements ShouldQueue
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
     * @param  Reject  $event
     * @return void
     */
    public function handle(Reject $event)
    {
        $trip = $event->trip;
        $from = $event->from;
        $to = $event->to;
        if ($to) {
            $notification = new RejectPassengerNotification();
            $notification->setAttribute('trip', $trip);
            $notification->setAttribute('from', $from);
            //$notification->setAttribute('token', $to);
            $notification->notify($to);
        }
    }
}
