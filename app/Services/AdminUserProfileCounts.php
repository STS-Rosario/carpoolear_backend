<?php

namespace STS\Services;

use STS\Models\Rating;
use STS\Models\User;
use STS\Repository\TripRepository;

class AdminUserProfileCounts
{
    public function __construct(private readonly TripRepository $tripRepository) {}

    public function tripsCount(User $viewer, User $subject): int
    {
        $userId = $subject->id;

        return $this->tripRepository->getTrips($viewer, $userId, true)->count()
            + $this->tripRepository->getTrips($viewer, $userId, false)->count()
            + $this->tripRepository->getOldTrips($viewer, $userId, true)->count()
            + $this->tripRepository->getOldTrips($viewer, $userId, false)->count();
    }

    public function ratingsCount(int $userId): int
    {
        return Rating::query()->where('user_id_to', $userId)->count()
            + Rating::query()->where('user_id_from', $userId)->count();
    }
}
