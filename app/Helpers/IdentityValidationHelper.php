<?php

namespace STS\Helpers;

use Carbon\Carbon;
use STS\Models\User;

class IdentityValidationHelper
{
    /**
     * Feature is on and we are in a period where unvalidated users are blocked on restricted actions.
     */
    public static function enforcementIsActive(): bool
    {
        if (! config('carpoolear.identity_validation_enabled', false)) {
            return false;
        }

        if (config('carpoolear.identity_validation_optional', false)) {
            return false;
        }

        return true;
    }

    /**
     * New users: created on or after identity_validation_new_users_date.
     */
    public static function newUsersCutoffDate(): ?Carbon
    {
        $raw = config('carpoolear.identity_validation_new_users_date', null);
        if (empty($raw)) {
            return null;
        }

        return Carbon::parse($raw)->startOfDay();
    }

    public static function isUserCreatedOnOrAfterCutoff(User $user): bool
    {
        $cutoff = self::newUsersCutoffDate();
        if (! $cutoff) {
            return false;
        }

        $createdAt = $user->created_at ? $user->created_at->copy()->startOfDay() : null;
        if (! $createdAt) {
            return false;
        }

        return $createdAt->gte($cutoff);
    }

    /**
     * User is "new" and must validate immediately (no validate_by_date grace) when enforcement is on.
     */
    public static function isNewUserRequiringValidation(User $user): bool
    {
        if (! self::enforcementIsActive()) {
            return false;
        }

        if (! config('carpoolear.identity_validation_required_new_users', false)) {
            return false;
        }

        if (! self::isUserCreatedOnOrAfterCutoff($user)) {
            return false;
        }

        if ($user->identity_validated) {
            return false;
        }

        // If a grace deadline exists (e.g. admin), use deadline rules instead
        if ($user->validate_by_date !== null) {
            return false;
        }

        return true;
    }

    /**
     * Pre-cutoff user with a validate_by_date that has passed.
     */
    public static function isCurrentUserPastDeadline(User $user): bool
    {
        if (! $user->validate_by_date) {
            return false;
        }

        if (self::isUserCreatedOnOrAfterCutoff($user)) {
            return false;
        }

        if ($user->identity_validated) {
            return false;
        }

        $end = $user->validate_by_date->copy()->endOfDay();

        return now()->gt($end);
    }

    /**
     * User is allowed to perform restricted actions (send message, request seat, accept/reject passenger, create trip).
     */
    public static function canPerformRestrictedActions(User $user): bool
    {
        if ($user->identity_validated) {
            return true;
        }

        if (! self::enforcementIsActive()) {
            return true;
        }

        if (self::isNewUserRequiringValidation($user)) {
            return false;
        }

        if (self::isCurrentUserPastDeadline($user)) {
            return false;
        }

        return true;
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
        return 'You must verify your account to perform this action.';
    }
}
