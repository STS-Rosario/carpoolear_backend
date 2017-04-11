<?php

namespace STS\Listeners\Notification;

use STS\Events\Passenger\Request;
use Illuminate\Contracts\Queue\ShouldQueue;
use STS\Notifications\RequestPassengerNotification;
use STS\Contracts\Repository\Trip as TripRepository;
use STS\Contracts\Repository\User as UserRepository;

class PassengerRequest implements ShouldQueue
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
     * @param  Request  $event
     * @return void
     */
    public function handle(Request $event)
    {
        $trip = $this->tripRepository->show($event->trip_id);
        $from = $this->userRepository->show($event->from_id);
        $to = $this->userRepository->show($event->to_id);
        if ($to) {
            $notification = new RequestPassengerNotification();
            $notification->setAttribute('trip', $trip);
            $notification->setAttribute('from', $from);
            //$notification->setAttribute('token', $to);
            $notification->notify($to);
        }
    }
}
