<?php

namespace Tests\Unit\Services;

use STS\Models\ManualIdentityValidation;
use STS\Services\ManualIdentityValidationResubmitPolicy;
use Tests\TestCase;

class ManualIdentityValidationResubmitPolicyTest extends TestCase
{
    private ManualIdentityValidationResubmitPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        config(['carpoolear.manual_identity_validation_max_submissions' => 3]);
        $this->policy = new ManualIdentityValidationResubmitPolicy;
    }

    public function test_max_submissions_reads_config_defaulting_to_three(): void
    {
        config(['carpoolear.manual_identity_validation_max_submissions' => 5]);

        $this->assertSame(5, $this->policy->maxSubmissions());
    }

    public function test_can_resubmit_when_paid_rejected_and_under_max_submissions(): void
    {
        $row = new ManualIdentityValidation([
            'paid' => true,
            'review_status' => ManualIdentityValidation::REVIEW_STATUS_REJECTED,
            'submission_count' => 1,
        ]);

        $this->assertTrue($this->policy->canResubmitWithoutPayment($row));
    }

    public function test_cannot_resubmit_when_submission_count_reached_max(): void
    {
        $row = new ManualIdentityValidation([
            'paid' => true,
            'review_status' => ManualIdentityValidation::REVIEW_STATUS_REJECTED,
            'submission_count' => 3,
        ]);

        $this->assertFalse($this->policy->canResubmitWithoutPayment($row));
    }

    public function test_cannot_resubmit_when_review_is_not_rejected(): void
    {
        $row = new ManualIdentityValidation([
            'paid' => true,
            'review_status' => ManualIdentityValidation::REVIEW_STATUS_PENDING,
            'submission_count' => 1,
        ]);

        $this->assertFalse($this->policy->canResubmitWithoutPayment($row));
    }

    public function test_requires_new_payment_when_latest_rejected_submissions_exhausted(): void
    {
        $row = new ManualIdentityValidation([
            'paid' => true,
            'review_status' => ManualIdentityValidation::REVIEW_STATUS_REJECTED,
            'submission_count' => 3,
        ]);

        $this->assertTrue($this->policy->requiresNewPayment($row));
    }

    public function test_does_not_require_new_payment_when_rejected_but_resubmits_remain(): void
    {
        $row = new ManualIdentityValidation([
            'paid' => true,
            'review_status' => ManualIdentityValidation::REVIEW_STATUS_REJECTED,
            'submission_count' => 2,
        ]);

        $this->assertFalse($this->policy->requiresNewPayment($row));
    }
}
