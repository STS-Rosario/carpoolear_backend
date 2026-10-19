<?php

namespace STS\Events\Trip;

use Illuminate\Queue\SerializesModels;
use STS\Events\Event;

class Update extends Event
{
    use SerializesModels;

    public $trip;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct($trip)
    {
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
