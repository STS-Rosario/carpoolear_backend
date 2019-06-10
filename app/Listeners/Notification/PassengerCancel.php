<?php

namespace STS\Listeners\Notification;

use STS\Events\Passenger\Cancel;
use Illuminate\Contracts\Queue\ShouldQueue;
use STS\Notifications\CancelPassengerNotification;

class PassengerCancel implements ShouldQueue
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
     * @param  Cancel  $event
     * @return void
     */
    public function handle(Cancel $event)
    {
        $trip = $event->trip;
        $from = $event->from;
        $to = $event->to;
        $state = $event->canceledState;
        if ($to) {
            $notification = new CancelPassengerNotification();
            $notification->setAttribute('trip', $trip);
            $notification->setAttribute('from', $from);
            $notification->setAttribute('canceledState', $state);
            $notification->notify($to);
        }
    }
}
