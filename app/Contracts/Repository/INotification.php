<?php

namespace STS\Contracts\Repository;

interface INotification
{
    public function getNotifications($user, $unread = false, $page_size = null, $page = null);

    public function markAsRead($notification);

    public function delete($notification);

    public function find($user, $id);
}
