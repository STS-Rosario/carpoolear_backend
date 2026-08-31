<?php

namespace STS\Services\Logic;

use STS\Repository\MessageRepository;
use STS\Repository\NotificationRepository;
use STS\Repository\PassengersRepository;
use STS\Repository\RatingRepository;
use STS\Support\NotificationCountCache;
use STS\Support\UserLocale;

class NotificationManager
{
    protected $repo;

    protected PassengersRepository $passengersRepository;

    protected RatingRepository $ratingRepository;

    protected MessageRepository $messageRepository;

    public function __construct(
        NotificationRepository $repo,
        ?PassengersRepository $passengersRepository = null,
        ?RatingRepository $ratingRepository = null,
        ?MessageRepository $messageRepository = null
    ) {
        $this->repo = $repo;
        $this->passengersRepository = $passengersRepository ?? new PassengersRepository;
        $this->ratingRepository = $ratingRepository ?? new RatingRepository;
        $this->messageRepository = $messageRepository ?? new MessageRepository;
    }

    public function getNotifications($user, $data)
    {
        $mark = false;
        if (isset($data['page'], $data['page_size'])) {
            $pageNumber = $data['page'] ?? null;
            $pageSize = $data['page_size'] ?? null;
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
            $texto = UserLocale::withLocale(
                UserLocale::resolve($user),
                fn () => $noti->toString()
            );
            $extras = $noti->getExtras();

            $row = [
                'id' => $n->id,
                'readed' => $n->read_at !== null,
                'created_at' => $n->created_at->toDateTimeString(),
                'text' => $texto,
                'extras' => $extras,
            ];
            $response[] = $row;

            if ($mark) {
                $this->repo->markAsRead($n);
            }
        }

        return $response;
    }

    public function getUnreadCount($user)
    {
        return NotificationCountCache::remember((int) $user->id, function () use ($user) {
            return $this->repo->countUnreadNotifications($user);
        });
    }

    public function getNavigationBadgeCounts($user): array
    {
        $pendingRequests = $this->passengersRepository->getPendingRequests(null, $user, []);
        $pendingRatings = $this->ratingRepository->getPendingRatings($user);

        return [
            'notifications' => $this->getUnreadCount($user),
            'messages' => $this->countUnreadConversations($user),
            'my_trips' => $pendingRequests->count() + $pendingRatings->count(),
        ];
    }

    protected function countUnreadConversations($user): int
    {
        return $this->messageRepository->countConversationsWithUnreadMessages($user);
    }

    public function delete($user, $id)
    {
        $notification = $this->repo->find($user, $id);
        if ($notification) {
            $this->repo->delete($notification);

            return true;
        }

        return false;
    }
}
