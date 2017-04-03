<?php

namespace STS\Contracts\Logic;

interface INotification
{
    public function getNotifications($user, $data);

    public function delete($user, $id);

    public function getUnreadCount($user);
}
