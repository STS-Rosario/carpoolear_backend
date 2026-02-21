<?php

namespace STS\Services;

use STS\Models\User;

/**
 * Service to anonymize user personal data and deactivate the account.
 * Keeps the user record for referential integrity (trips, messages, ratings).
 */
class AnonymizationService
{
    /**
     * Anonymize a user's personal info and deactivate the account.
     *
     * @param User $user
     * @return User
     */
    public function anonymize(User $user): User
    {
        $user->name = 'Usuario anónimo';
        $user->email = null;
        $user->birthday = null;
        $user->gender = null;
        $user->nro_doc = null;
        $user->description = null;
        $user->mobile_phone = null;
        $user->image = null;
        $user->account_number = null;
        $user->account_bank = null;
        $user->account_type = null;
        $user->active = 0;
        $user->save();

        return $user;
    }
}
