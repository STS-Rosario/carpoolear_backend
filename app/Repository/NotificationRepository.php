<?php

namespace STS\Repository;

use Carbon\Carbon;
use STS\Support\NotificationCountCache;

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

    public function countUnreadNotifications($user): int
    {
        return $user->unreadNotifications()->count();
    }

    public function markAsRead($notification = null)
    {
        if ($notification) {
            $notification->read_at = Carbon::now();
            $notification->save();
            NotificationCountCache::forget($notification->user_id);
        } else {
            $user->unreadNotifications()->update(['read_at' => Carbon::now()]);
        }
    }

    public function delete($notification)
    {
        $notification->deleted_at = Carbon::now();
        $notification->save();
        NotificationCountCache::forget($notification->user_id);
    }

    public function find($user, $id)
    {
        return $user->notifications()->where('id', $id)->first();
    }
}
