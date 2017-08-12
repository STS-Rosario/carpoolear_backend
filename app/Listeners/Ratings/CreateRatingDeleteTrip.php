<?php

namespace STS\Listeners\Ratings;

use STS\Entities\Passenger;
use STS\Events\Trip\Delete as DeleteEvent;
use Illuminate\Contracts\Queue\ShouldQueue;
use STS\Notifications\DeleteTripNotification;
use STS\Contracts\Repository\IRatingRepository;

class CreateRatingDeleteTrip implements ShouldQueue
{
    /**
     * Create the event listener.
     *
     * @return void
     */
    protected $ratingRepository;

    public function __construct(IRatingRepository $ratingRepository)
    {
        $this->ratingRepository = $ratingRepository;
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

        $passengers = $trip->passengerAccepted;
        if ($passengers->count() > 0) {
            foreach ($passengers as $passenger) {
                $passenger_hash = str_random(40);

                $rate = $this->ratingRepository->create($passenger->user_id, $trip->user_id, $trip->id, Passenger::TYPE_CONDUCTOR, Passenger::STATE_ACCEPTED, $passenger_hash);
                // event(new PendingEvent($passenger->user, $trip, $passenger_hash));

                $notification = new DeleteTripNotification();
                $notification->setAttribute('trip', $trip);
                $notification->setAttribute('from', $trip->user);
                $notification->setAttribute('hash', $passenger_hash);
                $notification->notify($passenger->user);
            }
        }
    }
}
