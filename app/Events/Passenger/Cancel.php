<?php

namespace STS\Events\Passenger;

use STS\Events\Event;
use Illuminate\Queue\SerializesModels;

class Cancel extends Event
{
    use SerializesModels;

    public $trip;
    public $from;
    public $to;
    public $canceledState;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct($trip, $from, $to, $state)
    {
        $this->trip = $trip;
        $this->from = $from;
        $this->to = $to;
        $this->canceledState = $state;
    }

    /**
     * Get the channels the event should be broadcast on.
     *
     * @return array
     */
    public function broadcastOn()
    {
        return [];
    }
}
