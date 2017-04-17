<?php

namespace STS\Events\Passenger;

use STS\Events\Event;
use Illuminate\Queue\SerializesModels;

class Reject extends Event
{
    // use SerializesModels;

    public $trip_id;
    public $from_id;
    public $to_id;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct($trip_id, $from_id, $to_id)
    {
        $this->trip_id = $trip_id;
        $this->from_id = $from_id;
        $this->to_id = $to_id;
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
