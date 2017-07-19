<?php

namespace STS\Events\Trip\Alert;

use STS\Events\Event;
use Illuminate\Queue\SerializesModels;

class HourLeft extends Event
{
    use SerializesModels;

    public $to;
    public $trip;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct($trip, $to)
    {
        $this->to = $to;
        $this->trip = $trip;
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
