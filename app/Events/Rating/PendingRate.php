<?php

namespace STS\Events\Rating;

use Illuminate\Queue\SerializesModels;
use STS\Events\Event;

class PendingRate extends Event
{
    use SerializesModels;

    public $to;

    public $trip;

    public $hash;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct($to, $trip, $hash)
    {
        $this->to = $to;
        $this->trip = $trip;
        $this->hash = $hash;
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
