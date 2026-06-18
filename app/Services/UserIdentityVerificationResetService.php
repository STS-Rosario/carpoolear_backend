<?php

namespace STS\Services;

use STS\Models\ManualIdentityValidation;
use STS\Models\MercadoPagoRejectedValidation;
use STS\Models\User;

class UserIdentityVerificationResetService
{
    public function clearForUser(User $user): void
    {
        $this->deleteManualIdentityValidationsForUser($user);
        MercadoPagoRejectedValidation::query()
            ->where('user_id', $user->id)
            ->delete();

        $user->identity_validated = false;
        $user->identity_validated_at = null;
        $user->identity_validation_type = null;
        $user->identity_validation_rejected_at = null;
        $user->identity_validation_reject_reason = null;
        $user->save();
    }

    private function deleteManualIdentityValidationsForUser(User $user): void
    {
        $records = ManualIdentityValidation::query()
            ->where('user_id', $user->id)
            ->get();

        ManualIdentityValidationDeletion::deleteRecords($records);
    }
}
