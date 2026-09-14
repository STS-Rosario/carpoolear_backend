<?php

namespace Tests\Unit\Services;

use STS\Models\ManualIdentityValidation;
use STS\Models\MercadoPagoRejectedValidation;
use STS\Models\User;
use STS\Services\UserIdentityVerificationSuccessService;
use Tests\TestCase;

class UserIdentityVerificationSuccessServiceTest extends TestCase
{
    public function test_apply_verification_clears_prior_rejection_state(): void
    {
        $user = User::factory()->create([
            'identity_validated' => false,
            'identity_validation_rejected_at' => now()->subDay(),
            'identity_validation_reject_reason' => 'name_mismatch',
        ]);

        $rejectedManual = ManualIdentityValidation::create([
            'user_id' => $user->id,
            'paid' => true,
            'paid_at' => now(),
            'submitted_at' => now(),
            'review_status' => ManualIdentityValidation::REVIEW_STATUS_REJECTED,
            'review_note' => 'Illegible documents.',
        ]);

        MercadoPagoRejectedValidation::create([
            'user_id' => $user->id,
            'reject_reason' => 'dni_mismatch',
            'mp_payload' => ['first_name' => 'Jane'],
        ]);

        app(UserIdentityVerificationSuccessService::class)->applyVerification($user, 'mercado_pago');

        $fresh = $user->fresh();
        $this->assertTrue((bool) $fresh->identity_validated);
        $this->assertSame('mercado_pago', $fresh->identity_validation_type);
        $this->assertNotNull($fresh->identity_validated_at);
        $this->assertNull($fresh->identity_validation_rejected_at);
        $this->assertNull($fresh->identity_validation_reject_reason);
        $this->assertDatabaseMissing('manual_identity_validations', ['id' => $rejectedManual->id]);
        $this->assertSame(0, MercadoPagoRejectedValidation::query()->where('user_id', $user->id)->count());
    }

    public function test_apply_verification_can_preserve_mercado_pago_rejected_validation_for_audit(): void
    {
        $user = User::factory()->create(['identity_validated' => false]);

        $preserved = MercadoPagoRejectedValidation::create([
            'user_id' => $user->id,
            'reject_reason' => 'dni_mismatch',
            'mp_payload' => ['first_name' => 'Jane'],
        ]);

        MercadoPagoRejectedValidation::create([
            'user_id' => $user->id,
            'reject_reason' => 'name_mismatch',
            'mp_payload' => ['first_name' => 'John'],
        ]);

        app(UserIdentityVerificationSuccessService::class)->applyVerification($user, 'manual', [
            'preserve_mercado_pago_rejected_validation_ids' => [$preserved->id],
        ]);

        $this->assertTrue((bool) $user->fresh()->identity_validated);
        $this->assertDatabaseHas('mercado_pago_rejected_validations', ['id' => $preserved->id]);
        $this->assertSame(1, MercadoPagoRejectedValidation::query()->where('user_id', $user->id)->count());
    }

    public function test_apply_verification_with_mercado_pago_closes_open_manual_validations(): void
    {
        $user = User::factory()->create(['identity_validated' => false]);
        $otherUser = User::factory()->create(['identity_validated' => false]);

        $pending = ManualIdentityValidation::create([
            'user_id' => $user->id,
            'paid' => true,
            'paid_at' => now(),
            'submitted_at' => now(),
            'review_status' => ManualIdentityValidation::REVIEW_STATUS_PENDING,
        ]);
        $awaitingPhotos = ManualIdentityValidation::create([
            'user_id' => $user->id,
            'paid' => true,
            'paid_at' => now(),
            'review_status' => ManualIdentityValidation::REVIEW_STATUS_AWAITING_PHOTOS,
        ]);
        $unpaid = ManualIdentityValidation::create([
            'user_id' => $user->id,
            'paid' => false,
            'review_status' => ManualIdentityValidation::REVIEW_STATUS_PENDING,
        ]);
        $approved = ManualIdentityValidation::create([
            'user_id' => $user->id,
            'paid' => true,
            'paid_at' => now(),
            'submitted_at' => now(),
            'review_status' => ManualIdentityValidation::REVIEW_STATUS_APPROVED,
        ]);
        $alreadyClosed = ManualIdentityValidation::create([
            'user_id' => $user->id,
            'paid' => true,
            'paid_at' => now(),
            'review_status' => ManualIdentityValidation::REVIEW_STATUS_CLOSED,
        ]);
        $historicalApprove = ManualIdentityValidation::create([
            'user_id' => $user->id,
            'paid' => true,
            'paid_at' => now(),
            'submitted_at' => now(),
            'review_status' => 'approve',
        ]);
        $historicalReject = ManualIdentityValidation::create([
            'user_id' => $user->id,
            'paid' => true,
            'paid_at' => now(),
            'submitted_at' => now(),
            'review_status' => 'reject',
        ]);
        $otherUserPending = ManualIdentityValidation::create([
            'user_id' => $otherUser->id,
            'paid' => true,
            'paid_at' => now(),
            'submitted_at' => now(),
            'review_status' => ManualIdentityValidation::REVIEW_STATUS_PENDING,
        ]);

        app(UserIdentityVerificationSuccessService::class)->applyVerification($user, 'mercado_pago');

        $this->assertSame(ManualIdentityValidation::REVIEW_STATUS_CLOSED, $pending->fresh()->review_status);
        $this->assertSame(ManualIdentityValidation::REVIEW_STATUS_CLOSED, $awaitingPhotos->fresh()->review_status);
        $this->assertSame(ManualIdentityValidation::REVIEW_STATUS_CLOSED, $unpaid->fresh()->review_status);
        $this->assertSame(ManualIdentityValidation::REVIEW_STATUS_APPROVED, $approved->fresh()->review_status);
        $this->assertSame(ManualIdentityValidation::REVIEW_STATUS_CLOSED, $alreadyClosed->fresh()->review_status);
        $this->assertSame('approve', $historicalApprove->fresh()->review_status);
        $this->assertSame('reject', $historicalReject->fresh()->review_status);
        $this->assertSame(ManualIdentityValidation::REVIEW_STATUS_PENDING, $otherUserPending->fresh()->review_status);
    }

    public function test_apply_verification_with_manual_does_not_close_open_manual_validations(): void
    {
        $user = User::factory()->create(['identity_validated' => false]);

        $pending = ManualIdentityValidation::create([
            'user_id' => $user->id,
            'paid' => true,
            'paid_at' => now(),
            'submitted_at' => now(),
            'review_status' => ManualIdentityValidation::REVIEW_STATUS_PENDING,
        ]);

        app(UserIdentityVerificationSuccessService::class)->applyVerification($user, 'manual');

        $this->assertSame(ManualIdentityValidation::REVIEW_STATUS_PENDING, $pending->fresh()->review_status);
    }
}
