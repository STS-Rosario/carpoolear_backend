<?php

namespace STS\Events\Trip\Alert;

use Illuminate\Queue\SerializesModels;
use STS\Events\Event;

class RequestNotAnswer extends Event
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
