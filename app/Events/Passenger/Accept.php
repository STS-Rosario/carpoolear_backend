<?php

namespace STS\Events\Passenger;

use STS\Events\Event;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class Accept extends Event
{
    use SerializesModels;

    protected $trip_id;
    protected $from_id;
    protected $to_id;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct($trip_id, $from_id, $to_id = null)
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
