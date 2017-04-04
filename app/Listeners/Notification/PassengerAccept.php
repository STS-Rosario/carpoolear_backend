<?php

namespace STS\Listeners\Notification;

use STS\Events\Passenger\Accept;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use STS\Contracts\Repository\IPassengersRepository;
use STS\Contracts\Repository\Trip as TripRepository;
use STS\Contracts\Repository\User as UserRepository;

use STS\Notifications\AcceptPassengerNotification;

class PassengerAccept implements ShouldQueue
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
     * @param  Accept  $event
     * @return void
     */
    public function handle(Accept $event)
    { 

        $trip = $this->tripRepository->show($event->trip_id);
        $from = $this->userRepository->show($event->from_id);
        $to   = $this->userRepository->show($event->to_id);
        if ($to) {
            $notification = new AcceptPassengerNotification();
            $notification->setAttribute('trip', $trip);
            $notification->setAttribute('from', $from);
            //$notification->setAttribute('token', $to);
            $notification->notify($to);
        }
    }
}
