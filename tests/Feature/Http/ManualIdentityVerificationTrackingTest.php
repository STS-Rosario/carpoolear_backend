<?php

namespace Tests\Feature\Http;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use MercadoPago\Resources\Preference;
use STS\Http\Middleware\UserAdmin;
use STS\Models\IdentityVerificationEvent;
use STS\Models\ManualIdentityValidation;
use STS\Models\MercadoPagoRejectedValidation;
use STS\Models\User;
use STS\Services\IdentityVerificationOutcome;
use STS\Services\MercadoPagoService;
use STS\Services\UserIdentityVerificationSuccessService;
use Tests\TestCase;

class ManualIdentityVerificationTrackingTest extends TestCase
{
    private function admin(): User
    {
        $user = User::factory()->create();
        $user->forceFill(['is_admin' => true])->saveQuietly();

        return $user->fresh();
    }

    private function enableManualPayment(): User
    {
        config([
            'carpoolear.identity_validation_enabled' => true,
            'carpoolear.identity_validation_manual_enabled' => true,
            'carpoolear.manual_identity_validation_cost_cents' => 500,
        ]);

        return User::factory()->create();
    }

    public function test_preference_create_records_payment_started(): void
    {
        $user = $this->enableManualPayment();
        $this->mock(MercadoPagoService::class, function ($mock) {
            $preference = new Preference;
            $preference->init_point = 'https://checkout.example/pay';
            $mock->shouldReceive('createPaymentPreferenceForManualValidation')->once()->andReturn($preference);
        });

        Log::spy();

        $this->actingAs($user, 'api')
            ->postJson('api/users/manual-identity-validation/preference')
            ->assertOk();

        $requestId = ManualIdentityValidation::where('user_id', $user->id)->value('id');
        $this->assertDatabaseHas('identity_verification_events', [
            'user_id' => $user->id,
            'method' => 'manual',
            'name' => 'payment_started',
            'related_id' => $requestId,
            'related_type' => 'manual_identity_validations',
        ]);

        $row = IdentityVerificationEvent::query()->where('user_id', $user->id)->first();
        $this->assertSame('checkout_pro', $row->metadata['payment_channel'] ?? null);

        Log::shouldHaveReceived('info')->withArgs(function (...$args): bool {
            return ($args[0] ?? null) === 'Identity verification manual payment_started reason=';
        })->once();
    }

    public function test_payment_success_redirect_records_payment_succeeded_once(): void
    {
        config(['services.mercadopago.oauth_frontend_redirect' => 'https://app.test']);
        $user = User::factory()->create();
        $row = ManualIdentityValidation::create([
            'user_id' => $user->id,
            'paid' => false,
            'review_status' => ManualIdentityValidation::REVIEW_STATUS_PENDING,
        ]);

        Log::spy();

        $this->get('api/mercadopago/manual-validation-success?request_id='.$row->id.'&payment_id=mp-1')
            ->assertRedirect();

        $this->assertDatabaseHas('identity_verification_events', [
            'user_id' => $user->id,
            'name' => 'payment_succeeded',
            'related_id' => $row->id,
        ]);

        $this->get('api/mercadopago/manual-validation-success?request_id='.$row->id.'&payment_id=mp-1')
            ->assertRedirect();

        $this->assertSame(1, IdentityVerificationEvent::query()->where('name', 'payment_succeeded')->count());
    }

    public function test_payment_failure_redirect_records_payment_failed(): void
    {
        config(['services.mercadopago.oauth_frontend_redirect' => 'https://app.test']);
        $user = User::factory()->create();
        $row = ManualIdentityValidation::create([
            'user_id' => $user->id,
            'paid' => false,
            'review_status' => ManualIdentityValidation::REVIEW_STATUS_PENDING,
        ]);

        Log::spy();

        $this->get('api/mercadopago/manual-validation-success?request_id='.$row->id.'&result=failure')
            ->assertRedirect();

        $this->assertDatabaseHas('identity_verification_events', [
            'user_id' => $user->id,
            'name' => 'payment_failed',
            'related_id' => $row->id,
        ]);

        Log::shouldHaveReceived('warning')->withArgs(function (...$args): bool {
            return ($args[0] ?? null) === 'Identity verification manual payment_failed reason=';
        })->once();
    }

    public function test_docs_submit_records_docs_submitted(): void
    {
        config([
            'carpoolear.identity_validation_enabled' => true,
            'carpoolear.identity_validation_manual_enabled' => true,
        ]);
        $user = User::factory()->create();
        $row = ManualIdentityValidation::create([
            'user_id' => $user->id,
            'paid' => true,
            'paid_at' => now(),
            'review_status' => ManualIdentityValidation::REVIEW_STATUS_AWAITING_PHOTOS,
        ]);

        Log::spy();

        $this->actingAs($user, 'api')->post('api/users/manual-identity-validation', [
            'request_id' => $row->id,
            'front_image' => UploadedFile::fake()->image('front.jpg', 100, 100)->size(500),
            'back_image' => UploadedFile::fake()->image('back.jpg', 100, 100)->size(500),
            'selfie_image' => UploadedFile::fake()->image('selfie.jpg', 100, 100)->size(500),
        ])->assertStatus(201);

        $this->assertDatabaseHas('identity_verification_events', [
            'user_id' => $user->id,
            'name' => 'docs_submitted',
            'related_id' => $row->id,
        ]);
    }

    public function test_invalid_upload_records_upload_rejected(): void
    {
        config([
            'carpoolear.identity_validation_enabled' => true,
            'carpoolear.identity_validation_manual_enabled' => true,
        ]);
        $user = User::factory()->create();
        $row = ManualIdentityValidation::create([
            'user_id' => $user->id,
            'paid' => true,
            'paid_at' => now(),
            'review_status' => ManualIdentityValidation::REVIEW_STATUS_AWAITING_PHOTOS,
        ]);

        $this->actingAs($user, 'api')->post('api/users/manual-identity-validation', [
            'request_id' => $row->id,
        ])->assertStatus(422);

        $this->assertDatabaseHas('identity_verification_events', [
            'user_id' => $user->id,
            'name' => 'upload_rejected',
            'related_id' => $row->id,
        ]);
    }

    public function test_admin_approve_records_manual_succeeded(): void
    {
        $admin = $this->admin();
        $user = User::factory()->create(['identity_validated' => false]);
        $row = ManualIdentityValidation::create([
            'user_id' => $user->id,
            'paid' => true,
            'paid_at' => now(),
            'submitted_at' => now(),
            'review_status' => ManualIdentityValidation::REVIEW_STATUS_PENDING,
        ]);

        $this->actingAs($admin, 'api');
        $this->withoutMiddleware(UserAdmin::class);

        Log::spy();

        $this->postJson('api/admin/manual-identity-validations/'.$row->id.'/review', [
            'action' => 'approve',
        ])->assertOk();

        $this->assertDatabaseHas('identity_verification_events', [
            'user_id' => $user->id,
            'method' => 'manual',
            'name' => 'succeeded',
            'related_id' => $row->id,
        ]);
    }

    public function test_admin_reject_requires_coded_reason_and_records_failed(): void
    {
        $admin = $this->admin();
        $user = User::factory()->create(['identity_validated' => false]);
        $row = ManualIdentityValidation::create([
            'user_id' => $user->id,
            'paid' => true,
            'paid_at' => now(),
            'submitted_at' => now(),
            'review_status' => ManualIdentityValidation::REVIEW_STATUS_PENDING,
        ]);

        $this->actingAs($admin, 'api');
        $this->withoutMiddleware(UserAdmin::class);

        $this->postJson('api/admin/manual-identity-validations/'.$row->id.'/review', [
            'action' => 'reject',
            'note' => 'Blurry photos.',
        ])->assertUnprocessable();

        $this->postJson('api/admin/manual-identity-validations/'.$row->id.'/review', [
            'action' => 'reject',
            'note' => 'Blurry photos.',
            'reject_reason' => IdentityVerificationOutcome::REASON_DOCS_ILLEGIBLE,
        ])->assertOk();

        $this->assertDatabaseHas('manual_identity_validations', [
            'id' => $row->id,
            'reject_reason' => 'docs_illegible',
        ]);
        $this->assertDatabaseHas('identity_verification_events', [
            'user_id' => $user->id,
            'name' => 'failed',
            'reason' => 'docs_illegible',
            'related_id' => $row->id,
        ]);
    }

    public function test_admin_pending_records_info_requested(): void
    {
        $admin = $this->admin();
        $user = User::factory()->create();
        $row = ManualIdentityValidation::create([
            'user_id' => $user->id,
            'paid' => true,
            'paid_at' => now(),
            'submitted_at' => now(),
            'review_status' => ManualIdentityValidation::REVIEW_STATUS_PENDING,
        ]);

        $this->actingAs($admin, 'api');
        $this->withoutMiddleware(UserAdmin::class);

        $this->postJson('api/admin/manual-identity-validations/'.$row->id.'/review', [
            'action' => 'pending',
            'note' => 'Need a clearer selfie.',
        ])->assertOk();

        $this->assertDatabaseHas('identity_verification_events', [
            'user_id' => $user->id,
            'name' => 'info_requested',
            'related_id' => $row->id,
        ]);
    }

    public function test_mp_success_records_closed_after_mp_success_for_open_manuals(): void
    {
        $user = User::factory()->create(['identity_validated' => false]);
        $open = ManualIdentityValidation::create([
            'user_id' => $user->id,
            'paid' => true,
            'paid_at' => now(),
            'submitted_at' => now(),
            'review_status' => ManualIdentityValidation::REVIEW_STATUS_PENDING,
        ]);

        app(UserIdentityVerificationSuccessService::class)->applyVerification($user, 'mercado_pago');

        $this->assertSame(ManualIdentityValidation::REVIEW_STATUS_CLOSED, $open->fresh()->review_status);
        $this->assertDatabaseHas('identity_verification_events', [
            'user_id' => $user->id,
            'method' => 'manual',
            'name' => 'closed_after_mp_success',
            'related_id' => $open->id,
        ]);
    }

    public function test_admin_approve_of_mp_rejection_records_manual_success_with_reason(): void
    {
        $admin = $this->admin();
        $user = User::factory()->create(['identity_validated' => false]);
        $row = MercadoPagoRejectedValidation::create([
            'user_id' => $user->id,
            'reject_reason' => 'dni_mismatch',
            'mp_payload' => ['first_name' => 'Jane'],
        ]);

        $this->actingAs($admin, 'api');
        $this->withoutMiddleware(UserAdmin::class);

        $this->postJson('api/admin/mercado-pago-rejected-validations/'.$row->id.'/review', [
            'action' => 'approve',
        ])->assertOk();

        $this->assertDatabaseHas('identity_verification_events', [
            'user_id' => $user->id,
            'method' => 'manual',
            'name' => 'succeeded',
            'reason' => IdentityVerificationOutcome::REASON_APPROVED_FROM_MP_REJECTION,
            'related_id' => $row->id,
            'related_type' => 'mercado_pago_rejected_validations',
        ]);
    }
}
