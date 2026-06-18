<?php

namespace STS\Services;

use STS\Models\ManualIdentityValidation;
use STS\Models\MercadoPagoRejectedValidation;
use STS\Models\User;

class UserIdentityVerificationSuccessService
{
    public function applyVerification(User $user, string $validationType): void
    {
        $this->clearPriorRejectionState($user);

        $user->identity_validated = true;
        $user->identity_validated_at = now();
        $user->identity_validation_type = $validationType;
        $user->identity_validation_rejected_at = null;
        $user->identity_validation_reject_reason = null;
        $user->save();
    }

    public function clearPriorRejectionState(User $user): void
    {
        MercadoPagoRejectedValidation::query()
            ->where('user_id', $user->id)
            ->delete();

        $this->deleteRejectedManualIdentityValidationsForUser($user);
    }

    private function deleteRejectedManualIdentityValidationsForUser(User $user): void
    {
        $records = ManualIdentityValidation::query()
            ->where('user_id', $user->id)
            ->where('review_status', ManualIdentityValidation::REVIEW_STATUS_REJECTED)
            ->get();

        ManualIdentityValidationDeletion::deleteRecords($records);
    }
}
