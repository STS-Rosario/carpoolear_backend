<?php

namespace STS\Repository;

use DB;
use STS\User;
use Carbon\Carbon;
use STS\Contracts\Repository\INotification;

class NotificationRepository implements INotification
{
    public function getNotifications($user, $unread = false, $page_size = null, $page = null)
    {
        if (!$unread) {
            $query = $user->notifications();
        } else {
            $query = $user->unreadNotifications();
        }
        if ($page_size && $page) {
            $query->take($page_size)->skip($page_size * ($page - 1));
        }
        return $query->get();
    }

    public function markAsRead($notification = null)
    {
        if ($notification) {
            $notification->read_at = Carbon::now();
            $notification->save();
        } else {
            $user->unreadNotifications()->update(['read_at' => Carbon::now() ]);
        }
    }

    public function delete($notification)
    {
        $notification->deleted_at = Carbon::now();
        $notification->save();
    }

    public function find($user, $id)
    {
        return $user->notifications()->where('id', $id)->first();
    }
}
