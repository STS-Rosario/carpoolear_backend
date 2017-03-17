<?php

namespace STS\Events\Trip;

use STS\Events\Event;
use Illuminate\Queue\SerializesModels;

class Update extends Event
{
    use SerializesModels;

    public $trip;
    public $enc_path;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct($trip, $enc_path)
    {
        $this->trip = $trip;
        $this->enc_path = $enc_path;
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
