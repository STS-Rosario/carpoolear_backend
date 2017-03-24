<?php

namespace STS\Services\Notifications;

use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Model;
use STS\Events\Notification\NotificationSending;
use Event;
use STS\User as UserModel;

class NotificationServices 
{  

    public function __construct()
    { 

    }


    public function driver($name)
    {
        return (new $name);
    }
 
    public function send($notification, $users, $channel)
    {
        $users = (is_array($users) ||  $users instanceof Collection) ? $users : [$users];
        $driver = $this->driver($channel);
        foreach ($users as $user) {
            if ($this->shouldSendNotification($notification, $user, $driver)) {
                $driver->send($notification, $user);
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
