<?php

namespace STS\Events\Passenger;

use Illuminate\Queue\SerializesModels;
use STS\Events\Event;

class AutoCancel extends Event
{
    use SerializesModels;

    public $trip;

    public $from;

    public $to;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct($trip, $from, $to)
    {
        $this->trip = $trip;
        $this->from = $from;
        $this->to = $to;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array
     */
    public function broadcastOn()
    {
        return [];
    }
}
