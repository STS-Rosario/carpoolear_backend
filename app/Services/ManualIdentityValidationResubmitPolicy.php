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
}
