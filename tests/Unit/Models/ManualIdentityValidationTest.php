<?php

namespace Tests\Unit\Models;

use Carbon\Carbon;
use STS\Models\ManualIdentityValidation;
use STS\Models\User;
use Tests\TestCase;

class ManualIdentityValidationTest extends TestCase
{
    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeRow(User $user, array $overrides = []): ManualIdentityValidation
    {
        return ManualIdentityValidation::query()->create(array_merge([
            'user_id' => $user->id,
            'submitted_at' => '2026-05-01 10:00:00',
            'front_image_path' => null,
            'back_image_path' => null,
            'selfie_image_path' => null,
            'payment_id' => null,
            'paid' => false,
            'paid_at' => null,
            'review_status' => ManualIdentityValidation::REVIEW_STATUS_PENDING,
            'reviewed_by' => null,
            'reviewed_at' => null,
            'review_note' => null,
            'manual_validation_started_at' => null,
        ], $overrides));
    }

    public function test_belongs_to_user_and_reviewed_by(): void
    {
        $applicant = User::factory()->create();
        $reviewer = User::factory()->create();

        $row = $this->makeRow($applicant, [
            'review_status' => ManualIdentityValidation::REVIEW_STATUS_APPROVED,
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => '2026-05-02 12:00:00',
        ]);

        $row = $row->fresh();
        $this->assertTrue($row->user->is($applicant));
        $this->assertTrue($row->reviewedBy->is($reviewer));
    }

    public function test_datetime_and_boolean_casts(): void
    {
        $user = User::factory()->create();

        $row = $this->makeRow($user, [
            'submitted_at' => '2026-06-15 08:30:00',
            'paid' => 1,
            'paid_at' => '2026-06-15 09:00:00',
            'reviewed_at' => '2026-06-16 11:00:00',
            'manual_validation_started_at' => '2026-06-16 10:00:00',
        ]);

        $row = $row->fresh();
        $this->assertInstanceOf(Carbon::class, $row->submitted_at);
        $this->assertTrue($row->paid);
        $this->assertInstanceOf(Carbon::class, $row->paid_at);
        $this->assertInstanceOf(Carbon::class, $row->reviewed_at);
        $this->assertInstanceOf(Carbon::class, $row->manual_validation_started_at);
    }

    public function test_has_images_detects_any_uploaded_path(): void
    {
        $user = User::factory()->create();

        $empty = $this->makeRow($user);
        $this->assertFalse($empty->fresh()->hasImages());

        $front = $this->makeRow($user, ['front_image_path' => 'manual/1/front.jpg']);
        $this->assertTrue($front->fresh()->hasImages());

        $backOnly = $this->makeRow($user, [
            'back_image_path' => 'manual/2/back.jpg',
            'front_image_path' => null,
        ]);
        $this->assertTrue($backOnly->fresh()->hasImages());

        $selfie = $this->makeRow($user, [
            'selfie_image_path' => 'manual/3/selfie.jpg',
            'front_image_path' => null,
            'back_image_path' => null,
        ]);
        $this->assertTrue($selfie->fresh()->hasImages());
    }

    public function test_review_status_string_constants(): void
    {
        $this->assertSame('pending', ManualIdentityValidation::REVIEW_STATUS_PENDING);
        $this->assertSame('approved', ManualIdentityValidation::REVIEW_STATUS_APPROVED);
        $this->assertSame('rejected', ManualIdentityValidation::REVIEW_STATUS_REJECTED);
    }

    public function test_table_name_is_manual_identity_validations(): void
    {
        $this->assertSame('manual_identity_validations', (new ManualIdentityValidation)->getTable());
    }
}
