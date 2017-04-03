<?php

namespace STS\Listeners\Notification;

use STS\Events\Passenger\Request;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use STS\Contracts\Repository\IPassengersRepository;
use STS\Contracts\Repository\Trip as TripRepository;

class PassengerRequest implements ShouldQueue
{
    protected $passengerRepository;
    protected $tripRepository;

    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct(TripRepository $tripRepository, IPassengersRepository $passengerRepository)
    {
        $this->passengerRepository = $passengerRepository;
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
        //
    }
}
