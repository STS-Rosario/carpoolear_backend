<?php

namespace STS\Events\User;

use Illuminate\Queue\SerializesModels;
use STS\Events\Event;

class Update extends Event
{
    use SerializesModels;

    protected $id;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct($id)
    {
        $this->id = $id;
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
