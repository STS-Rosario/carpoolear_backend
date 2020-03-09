<?php

namespace STS\Listeners\Notification;

use STS\Events\Passenger\AutoRequest;
use Illuminate\Contracts\Queue\ShouldQueue;
use STS\Notifications\AutoRequestPassengerNotification;

class PassengerAutoRequest implements ShouldQueue
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
     * @param  AutoRequest  $event
     * @return void
     */
    public function handle(AutoRequest $event)
    {
        $trip = $event->trip;
        $from = $event->from;
        $to = $event->to;
        if ($to) {
            $notification = new AutoRequestPassengerNotification();
            $notification->setAttribute('trip', $trip);
            $notification->setAttribute('from', $from);
            //$notification->setAttribute('token', $to);
            $notification->notify($to);
        }
    }
}
