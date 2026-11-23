<?php

namespace STS\Services;

use STS\Models\ManualIdentityValidation;
use STS\Models\MercadoPagoRejectedValidation;
use STS\Models\User;

class UserIdentityVerificationSuccessService
{
    public function applyVerification(User $user, string $validationType, array $options = []): void
    {
        $this->clearPriorRejectionState($user, $options);

        $user->identity_validated = true;
        $user->identity_validated_at = now();
        $user->identity_validation_type = $validationType;
        $user->identity_validation_rejected_at = null;
        $user->identity_validation_reject_reason = null;
        $user->save();

        $outcomeName = $validationType === 'mercado_pago'
            ? IdentityVerificationOutcome::NAME_CLOSED_AFTER_MP_SUCCESS
            : IdentityVerificationOutcome::NAME_CLOSED_AFTER_USER_VERIFIED;

        $this->closeOpenManualIdentityValidationsForUser($user, array_merge($options, [
            'outcome_name' => $outcomeName,
        ]));
    }

    /**
     * @param  array{except_manual_validation_ids?: list<int>, outcome_name?: string}  $options
     * @return int Number of manual rows closed
     */
    public function closeOpenManualIdentityValidationsForUser(User $user, array $options = []): int
    {
        $exceptIds = array_map('intval', $options['except_manual_validation_ids'] ?? []);
        $outcomeName = $options['outcome_name'] ?? IdentityVerificationOutcome::NAME_CLOSED_AFTER_USER_VERIFIED;

        $open = ManualIdentityValidation::query()
            ->where('user_id', $user->id)
            ->when($exceptIds !== [], fn ($query) => $query->whereNotIn('id', $exceptIds))
            ->where(function ($query) {
                $query->whereNull('review_status')
                    ->orWhereNotIn('review_status', ManualIdentityValidation::resolvedReviewStatusAliases());
            })
            ->get();

        if (! empty($options['dry_run'])) {
            return $open->count();
        }

        $outcome = app(IdentityVerificationOutcome::class);
        $closed = 0;
        foreach ($open as $item) {
            $item->review_status = ManualIdentityValidation::REVIEW_STATUS_CLOSED;
            $item->save();
            $outcome->emit([
                'user_id' => $user->id,
                'method' => IdentityVerificationOutcome::METHOD_MANUAL,
                'name' => $outcomeName,
                'related_type' => 'manual_identity_validations',
                'related_id' => $item->id,
            ]);
            $closed++;
        }

        return $closed;
    }

    public function clearPriorRejectionState(User $user, array $options = []): void
    {
        $preserveMpRejectedIds = $options['preserve_mercado_pago_rejected_validation_ids'] ?? [];

        $mpRejectedQuery = MercadoPagoRejectedValidation::query()->where('user_id', $user->id);
        if ($preserveMpRejectedIds !== []) {
            $mpRejectedQuery->whereNotIn('id', $preserveMpRejectedIds);
        }
        $mpRejectedQuery->delete();

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