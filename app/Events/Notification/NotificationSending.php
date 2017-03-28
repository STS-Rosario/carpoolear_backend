<?php

namespace STS\Events\Notification;

use STS\Events\Event;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class NotificationSending extends Event
{
    use SerializesModels;

    public $notification;

    public $user;

    public $channel;

    public $device;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct($notification, $user, $channel, $device = null)
    {
        $this->notification = $notification;
        $this->user = $user;
        $this->channel = $channel;
        $this->device = $device;
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
