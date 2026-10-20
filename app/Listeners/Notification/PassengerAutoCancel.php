<?php

namespace STS\Listeners\Notification;

use Illuminate\Contracts\Queue\ShouldQueue;
use STS\Events\Passenger\AutoCancel;
use STS\Notifications\AutoCancelPassengerRequestIfRequestLimitedNotification;
use STS\Notifications\AutoCancelRequestIfRequestLimitedNotification;

class PassengerAutoCancel implements ShouldQueue
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
    public function handle(AutoCancel $event)
    {
        $trip = $event->trip;
        $from = $event->from;
        $to = $event->to;
        if ($from) {
            $notification_owner = new AutoCancelPassengerRequestIfRequestLimitedNotification;
            $notification_owner->setAttribute('trip', $trip);
            $notification_owner->setAttribute('from', $to);
            $notification_owner->notify($from);
        }
        if ($to) {
            $notification = new AutoCancelRequestIfRequestLimitedNotification;
            $notification->setAttribute('trip', $trip);
            $notification->setAttribute('from', $from);
            $notification->notify($to);
        }
    }
}
