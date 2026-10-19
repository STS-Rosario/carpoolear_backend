<?php

namespace STS\Services;

use Illuminate\Support\Facades\DB;
use STS\Models\User;

/**
 * Service for hard-deleting users who have no trips, ratings, or references.
 * All related records are removed via foreign key cascades when the user is deleted.
 */
class UserDeletionService
{
    /**
     * Delete a user and all related data. Use only for users with no trips, ratings, or references.
     */
    public function deleteUser(User $user): bool
    {
        return DB::transaction(function () use ($user) {
            $userId = $user->id;
            User::destroy($userId);

            return true;
        });
    }
}
