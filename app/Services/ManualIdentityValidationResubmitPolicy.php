<?php

namespace STS\Services;

use STS\Models\ManualIdentityValidation;

class ManualIdentityValidationResubmitPolicy
{
    public function maxSubmissions(): int
    {
        return (int) config('carpoolear.manual_identity_validation_max_submissions', 3);
    }

    public function canResubmitWithoutPayment(ManualIdentityValidation $row): bool
    {
        if (! $row->paid) {
            return false;
        }

        if ($row->review_status !== ManualIdentityValidation::REVIEW_STATUS_REJECTED) {
            return false;
        }

        return (int) ($row->submission_count ?? 0) < $this->maxSubmissions();
    }

    public function requiresNewPayment(?ManualIdentityValidation $latest): bool
    {
        if (! $latest || ! $latest->paid) {
            return false;
        }

        return $latest->review_status === ManualIdentityValidation::REVIEW_STATUS_REJECTED
            && (int) ($latest->submission_count ?? 0) >= $this->maxSubmissions();
    }

    /**
     * @return array<string, mixed>
     */
    public function statusPayload(?ManualIdentityValidation $latest): array
    {
        $maxSubmissions = $this->maxSubmissions();

        if (! $latest) {
            return [
                'has_submission' => false,
                'request_id' => null,
                'paid' => null,
                'paid_at' => null,
                'review_status' => null,
                'submitted_at' => null,
                'review_note' => null,
                'submission_count' => null,
                'max_submissions' => $maxSubmissions,
                'can_resubmit_without_payment' => false,
            ];
        }

        return [
            'has_submission' => true,
            'request_id' => $latest->id,
            'paid' => $latest->paid,
            'paid_at' => $latest->paid_at ? $latest->paid_at->toDateTimeString() : null,
            'review_status' => $latest->review_status,
            'submitted_at' => $latest->submitted_at ? $latest->submitted_at->toDateTimeString() : null,
            'review_note' => $latest->review_note,
            'submission_count' => (int) $latest->submission_count,
            'max_submissions' => $maxSubmissions,
            'can_resubmit_without_payment' => $this->canResubmitWithoutPayment($latest),
        ];
    }
}
