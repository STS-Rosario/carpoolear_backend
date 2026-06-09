<?php

namespace STS\Listeners\Ratings;

use Illuminate\Support\Str;
use STS\Events\Trip\Delete as DeleteEvent;
use STS\Models\Passenger;
use STS\Notifications\DeleteTripNotification;
use STS\Repository\RatingRepository;

class CreateRatingDeleteTrip
{
    /**
     * Create the event listener.
     *
     * @return void
     */
    protected $ratingRepository;

    public function __construct(RatingRepository $ratingRepository)
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

        $passengerUserIdsNotified = [];

        foreach ($trip->passenger()->orderBy('created_at', 'desc')->get() as $passenger) {
            $requestState = (int) $passenger->request_state;
            $inRatingState = $requestState === Passenger::STATE_ACCEPTED || $requestState === Passenger::STATE_CANCELED;

            $canceledButAccepted = true;
            if ($requestState === Passenger::STATE_CANCELED) {
                if (isset($passenger->canceled_state) && (int) $passenger->canceled_state === Passenger::CANCELED_REQUEST) {
                    $canceledButAccepted = false;
                }
            }

            if (! $inRatingState || ! $canceledButAccepted) {
                continue;
            }

            if (in_array($passenger->user_id, $passengerUserIdsNotified, true)) {
                continue;
            }

            $passenger_hash = Str::random(40);
            $this->ratingRepository->create($passenger->user_id, $trip->user_id, $trip->id, Passenger::TYPE_CONDUCTOR, Passenger::STATE_ACCEPTED, $passenger_hash);

            $notification = new DeleteTripNotification;
            $notification->setAttribute('trip', $trip);
            $notification->setAttribute('from', $trip->user);
            $notification->setAttribute('hash', $passenger_hash);
            $notification->notify($passenger->user);

            $passengerUserIdsNotified[] = $passenger->user_id;
        }
    }
}
