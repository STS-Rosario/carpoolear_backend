<?php

namespace STS\Helpers;

use STS\Models\User;

class IdentityValidationHelper
{
    /**
     * User is "new" (created on or after identity_validation_new_users_date) and has no validate_by_date (extra-time deadline).
     * When identity_validation_required_new_users is true, such users must validate before performing restricted actions.
     */
    public static function isNewUserRequiringValidation(User $user): bool
    {
        if (! config('carpoolear.identity_validation_required_new_users', false)) {
            return false;
        }

        $cutoffDate = config('carpoolear.identity_validation_new_users_date');
        if (empty($cutoffDate)) {
            return false;
        }

        $cutoff = \Carbon\Carbon::parse($cutoffDate)->startOfDay();
        $createdAt = $user->created_at ? $user->created_at->startOfDay() : null;
        if (! $createdAt || $createdAt->lt($cutoff)) {
            return false;
        }

        return $user->validate_by_date === null;
    }

    /**
     * User is allowed to perform restricted actions (send message, request seat, accept/reject passenger, create trip).
     */
    public static function canPerformRestrictedActions(User $user): bool
    {
        if ($user->identity_validated) {
            return true;
        }

        return ! self::isNewUserRequiringValidation($user);
    }

    /**
     * Error array for ExceptionWithErrors when user cannot perform restricted actions.
     * Frontend checks with checkError(error, 'identity_validation_required').
     */
    public static function identityValidationRequiredError(): array
    {
        return ['error' => ['identity_validation_required']];
    }

    public static function identityValidationRequiredMessage(): string
    {
        return 'You must validate your identity to perform this action.';
    }
}
