<?php

namespace STS\Listeners\Notification;

use STS\Events\Passenger\Reject;
use Illuminate\Contracts\Queue\ShouldQueue;
use STS\Notifications\RejectPassengerNotification;
use STS\Contracts\Repository\Trip as TripRepository;
use STS\Contracts\Repository\User as UserRepository;

class PassengerReject implements ShouldQueue
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
     * @param  Reject  $event
     * @return void
     */
    public function handle(Reject $event)
    {
        $trip = $this->tripRepository->show($event->trip_id);
        $from = $this->userRepository->show($event->from_id);
        $to = $this->userRepository->show($event->to_id);
        if ($to) {
            $notification = new RejectPassengerNotification();
            $notification->setAttribute('trip', $trip);
            $notification->setAttribute('from', $from);
            //$notification->setAttribute('token', $to);
            $notification->notify($to);
        }
    }
}
