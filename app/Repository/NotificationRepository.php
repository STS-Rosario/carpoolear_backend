<?php

namespace STS\Repository;

use Carbon\Carbon;

class NotificationRepository
{
    public function getNotifications($user, $unread = false, $page_size = null, $page = null)
    {
        if (! $unread) {
            $query = $user->notifications()->orderBy('created_at', 'desc');
        } else {
            $query = $user->unreadNotifications()->orderBy('created_at', 'desc');
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
            $user->unreadNotifications()->update(['read_at' => Carbon::now()]);
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
