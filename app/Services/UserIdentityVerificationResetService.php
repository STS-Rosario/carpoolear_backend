<?php

namespace STS\Services;

use STS\Models\ManualIdentityValidation;
use STS\Models\MercadoPagoRejectedValidation;
use STS\Models\User;

class UserIdentityVerificationResetService
{
    public function clearForUser(User $user): void
    {
        $previousValidated = (bool) $user->identity_validated;
        $previousValidationType = $user->identity_validation_type;

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

        app(IdentityVerificationOutcome::class)->emit([
            'user_id' => $user->id,
            'method' => IdentityVerificationOutcome::METHOD_ADMIN,
            'name' => IdentityVerificationOutcome::NAME_VERIFICATION_RESET,
            'related_type' => 'users',
            'related_id' => $user->id,
            'metadata' => [
                'previous_validated' => $previousValidated,
                'previous_validation_type' => $previousValidationType,
                'admin_id' => auth()->id(),
            ],
        ]);
    }

    private function deleteManualIdentityValidationsForUser(User $user): void
    {
        $records = ManualIdentityValidation::query()
            ->where('user_id', $user->id)
            ->get();

        ManualIdentityValidationDeletion::deleteRecords($records);
    }
}
