<?php

namespace STS\Events;

use STS\Events\Event;
use Illuminate\Queue\SerializesModels;

class TestEvent extends Event
{
    use SerializesModels; 

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct()
    {
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
