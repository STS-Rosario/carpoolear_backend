<?php

namespace STS\Listeners\Notification;

use STS\Events\Passenger\Cancel;
use Illuminate\Contracts\Queue\ShouldQueue;
use STS\Notifications\CancelPassengerNotification;
use STS\Contracts\Repository\Trip as TripRepository;
use STS\Contracts\Repository\User as UserRepository;

class PassengerCancel implements ShouldQueue
{
    protected $userRepository;
    protected $tripRepository;

    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct(TripRepository $tripRepository, UserRepository $userRepository)
    {
        $this->userRepository = $userRepository;
        $this->tripRepository = $tripRepository;
    }

    /**
     * Handle the event.
     *
     * @param  Cancel  $event
     * @return void
     */
    public function handle(Cancel $event)
    {
        $trip = $this->tripRepository->show($event->trip_id);
        $from = $this->userRepository->show($event->from_id);
        $to = $this->userRepository->show($event->to_id);
        if ($to) {
            $notification = new CancelPassengerNotification();
            $notification->setAttribute('trip', $trip);
            $notification->setAttribute('from', $from);
            //$notification->setAttribute('token', $to);
            $notification->notify($to);
        }
    }
}
