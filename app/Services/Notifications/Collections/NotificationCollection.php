<?php

namespace STS\Services\Notifications\Collections;

use Illuminate\Database\Eloquent\Collection;

class NotificationCollection extends Collection
{
    public function asNotifications()
    {
        $colection = new Collection();
        foreach ($this as $notification) {
            $colection->push($notification->asNotification());
        }

        return $colection;
    }
}
