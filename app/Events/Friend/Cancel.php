<?php

namespace STS\Events\Friend;

use STS\Events\Event;
use Illuminate\Queue\SerializesModels;

class Cancel extends Event
{
    use SerializesModels;

    public $from;

    public $to;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct($from, $to)
    {
        $this->from = $from;
        $this->to = $to;
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
