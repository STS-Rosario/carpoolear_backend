<?php

namespace STS\Events\User;

use STS\Events\Event;
use Illuminate\Queue\SerializesModels;

class Reset extends Event
{
    use SerializesModels;
    public $id;
    public $token;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct($id, $token)
    {
        $this->id = $id;
        $this->token = $token;
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
