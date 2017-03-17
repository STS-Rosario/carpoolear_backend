<?php

namespace STS\Events\Trip;

use Illuminate\Queue\SerializesModels;
use STS\Events\Event;

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
