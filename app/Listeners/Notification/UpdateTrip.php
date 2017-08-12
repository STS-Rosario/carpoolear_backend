<?php

namespace STS\Listeners\Notification;

use STS\Events\Trip\Update as UpdateEvent;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use STS\Notifications\UpdateTripNotification;

class UpdateTrip implements ShouldQueue
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
     * @param  Update  $event
     * @return void
     */
    public function handle(UpdateEvent $event)
    {
        $trip = $event->trip;
        $passengers = $trip->passengerAccepted;
        if ($passengers->count() > 0) {
            foreach($passengers as $passenger) {
                $notification = new UpdateTripNotification();
                $notification->setAttribute('trip', $trip);
                $notification->setAttribute('from', $trip->user);
                $notification->notify($passenger->user);

            }
        }
    }
}
