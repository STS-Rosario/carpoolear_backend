<?php

namespace STS\Services\Notifications;

use Event;
use Illuminate\Support\Collection;
use STS\Events\Notification\NotificationSending;

class NotificationServices
{
    public function __construct()
    {
    }

    public function driver($name)
    {
        return new $name;
    }

    public function send($notification, $users, $channel)
    {
        $users = (is_array($users) || $users instanceof Collection) ? $users : [$users];
        $driver = $this->driver($channel);
        foreach ($users as $user) {
            if ($this->shouldSendNotification($notification, $user, $driver)) {
                try {
                    $driver->send($notification, $user);
                } catch (\Exception $ex) {
                    
                }
            }
        }
    }

    protected function shouldSendNotification($notification, $user, $channel)
    {
        return Event::until(
            new NotificationSending($notification, $user, $channel)
        ) !== false;
    }
}
