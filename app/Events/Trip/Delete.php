<?php

namespace STS\Events\Trip;

use STS\Events\Event;
use Illuminate\Queue\SerializesModels;

class Delete extends Event
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
        console_log('Evento');
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
