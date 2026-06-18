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
}
