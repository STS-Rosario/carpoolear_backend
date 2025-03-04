<?php

namespace STS\Services\Logic;
use STS\Repository\NotificationRepository;
 

class NotificationManager
{
    protected $repo;

    public function __construct(NotificationRepository $repo)
    {
        $this->repo = $repo;
    }

    public function getNotifications($user, $data)
    {
        $mark = false;
        if (isset($data['page']) && isset($data['page_size'])) {
            $pageNumber = isset($data['page']) ? $data['page'] : null;
            $pageSize = isset($data['page_size']) ? $data['page_size'] : null;
            $notifications = $this->repo->getNotifications($user, false, $pageSize, $pageNumber);
        } else {
            $notifications = $this->repo->getNotifications($user, false);
        }

        if (isset($data['mark']) && parse_boolean($data['mark'])) {
            $mark = true;
        }

        $response = [];
        foreach ($notifications as $n) {
            $noti = $n->asNotification();
            $texto = $noti->toString();
            $extras = $noti->getExtras();

            $data = [
                'id' => $n->id,
                'readed' => $n->read_at != null,
                'created_at' => $n->created_at->toDateTimeString(),
                'text' => $texto,
                'extras' => $extras,
            ];
            $response[] = $data; // array_merge($data, $n->attributes());

            if ($mark) {
                $this->repo->markAsRead($n);
            }
        }

        return $response;
    }

    public function getUnreadCount($user)
    {
        return $this->repo->getNotifications($user, true)->count();
    }

    public function delete($user, $id)
    {
        $notification = $this->repo->find($user, $id);
        if ($notification) {
            return $this->repo->delete($notification);
        } else {
            return;
        }
    }
}
