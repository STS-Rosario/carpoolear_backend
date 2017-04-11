<?php

namespace STS\Events\Rating;

use STS\Events\Event;
use Illuminate\Queue\SerializesModels;

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
