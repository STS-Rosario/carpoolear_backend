<?php

namespace STS\Services;

use STS\Models\Rating;

class AdminUserProfileCounts
{
    public function ratingsCount(int $userId): int
    {
        return Rating::query()->where('user_id_to', $userId)->count()
            + Rating::query()->where('user_id_from', $userId)->count();
    }
}
