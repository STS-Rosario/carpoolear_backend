<?php

namespace Tests\Unit\Services;

use Illuminate\Support\Facades\Log;
use STS\Models\IdentityVerificationEvent;
use STS\Models\ManualIdentityValidation;
use STS\Models\MercadoPagoRejectedValidation;
use STS\Models\User;
use STS\Services\IdentityVerificationEventRecorder;
use STS\Services\IdentityVerificationOutcome;
use STS\Services\UserIdentityVerificationSuccessService;
use Tests\TestCase;

class IdentityVerificationOutcomeTest extends TestCase
{
    public function test_emit_persists_event_and_logs_structured_message(): void
    {
        $user = User::factory()->create();
        Log::spy();

        app(IdentityVerificationOutcome::class)->emit([
            'user_id' => $user->id,
            'method' => IdentityVerificationOutcome::METHOD_MERCADO_PAGO,
            'name' => IdentityVerificationOutcome::NAME_FAILED,
            'reason' => IdentityVerificationOutcome::REASON_OAUTH_CANCELLED,
            'attempt_id' => '11111111-1111-1111-1111-111111111111',
            'surface' => 'choice_cards',
            'metadata' => ['mp_error' => 'access_denied'],
        ]);

        $this->assertDatabaseHas('identity_verification_events', [
            'user_id' => $user->id,
            'method' => 'mercado_pago',
            'name' => 'failed',
            'reason' => 'oauth_cancelled',
            'attempt_id' => '11111111-1111-1111-1111-111111111111',
            'surface' => 'choice_cards',
        ]);

        $row = IdentityVerificationEvent::query()->where('user_id', $user->id)->first();
        $this->assertSame(['mp_error' => 'access_denied'], $row->metadata);

        Log::shouldHaveReceived('warning')->withArgs(function (...$args) use ($user): bool {
            return ($args[0] ?? null) === 'Identity verification mercado_pago failed reason=oauth_cancelled'
                && (int) ($args[1]['user_id'] ?? 0) === $user->id
                && ($args[1]['attempt_id'] ?? null) === '11111111-1111-1111-1111-111111111111'
                && ($args[1]['reason'] ?? null) === 'oauth_cancelled'
                && ($args[1]['mp_error'] ?? null) === 'access_denied';
        })->once();
    }

    public function test_emit_logs_success_at_info_without_reason_suffix_value(): void
    {
        $user = User::factory()->create();
        Log::spy();

        app(IdentityVerificationOutcome::class)->emit([
            'user_id' => $user->id,
            'method' => IdentityVerificationOutcome::METHOD_MERCADO_PAGO,
            'name' => IdentityVerificationOutcome::NAME_SUCCEEDED,
            'attempt_id' => '22222222-2222-2222-2222-222222222222',
        ]);

        $this->assertDatabaseHas('identity_verification_events', [
            'user_id' => $user->id,
            'name' => 'succeeded',
            'reason' => null,
        ]);

        Log::shouldHaveReceived('info')->withArgs(function (...$args): bool {
            return ($args[0] ?? null) === 'Identity verification mercado_pago succeeded reason=';
        })->once();
    }

    public function test_emit_logs_token_exchange_failure_at_error_level(): void
    {
        $user = User::factory()->create();
        Log::spy();

        app(IdentityVerificationOutcome::class)->emit([
            'user_id' => $user->id,
            'method' => IdentityVerificationOutcome::METHOD_MERCADO_PAGO,
            'name' => IdentityVerificationOutcome::NAME_FAILED,
            'reason' => IdentityVerificationOutcome::REASON_TOKEN_EXCHANGE_FAILED,
            'metadata' => ['http_status' => 400],
        ]);

        Log::shouldHaveReceived('error')->withArgs(function (...$args): bool {
            return ($args[0] ?? null) === 'Identity verification mercado_pago failed reason=token_exchange_failed'
                && ($args[1]['http_status'] ?? null) === 400;
        })->once();
    }

    public function test_emit_strips_pii_keys_from_metadata_and_logs(): void
    {
        $user = User::factory()->create();
        Log::spy();

        app(IdentityVerificationOutcome::class)->emit([
            'user_id' => $user->id,
            'method' => IdentityVerificationOutcome::METHOD_MERCADO_PAGO,
            'name' => IdentityVerificationOutcome::NAME_FAILED,
            'reason' => IdentityVerificationOutcome::REASON_DNI_MISMATCH,
            'metadata' => [
                'user_name' => 'Jane Doe',
                'mp_name' => 'Other Person',
                'user_dni' => '30123456',
                'mp_dni' => '30999999',
                'nro_doc' => '30123456',
                'identification' => ['number' => '30123456'],
                'mp_payload' => ['first_name' => 'Jane'],
                'http_status' => 200,
            ],
        ]);

        $row = IdentityVerificationEvent::query()->where('user_id', $user->id)->first();
        $this->assertSame(['http_status' => 200], $row->metadata);
        $this->assertArrayNotHasKey('user_name', $row->metadata);
        $this->assertArrayNotHasKey('mp_payload', $row->metadata);

        Log::shouldHaveReceived('warning')->withArgs(function (...$args): bool {
            $context = $args[1] ?? [];

            return ! array_key_exists('user_name', $context)
                && ! array_key_exists('mp_payload', $context)
                && ($context['http_status'] ?? null) === 200;
        })->once();
    }

    public function test_emit_still_logs_outcome_when_event_insert_fails(): void
    {
        Log::spy();

        app(IdentityVerificationOutcome::class)->emit([
            'user_id' => 9_999_999,
            'method' => IdentityVerificationOutcome::METHOD_MANUAL,
            'name' => IdentityVerificationOutcome::NAME_FAILED,
            'reason' => IdentityVerificationOutcome::REASON_DOCS_ILLEGIBLE,
        ]);

        $this->assertSame(0, IdentityVerificationEvent::query()->count());

        Log::shouldHaveReceived('warning')->withArgs(function (...$args): bool {
            return ($args[0] ?? null) === 'Identity verification manual failed reason=docs_illegible';
        })->once();

        Log::shouldHaveReceived('error')->withArgs(function (...$args): bool {
            return ($args[0] ?? null) === 'Identity verification event insert failed'
                && is_string($args[1]['message'] ?? null)
                && ($args[1]['message'] ?? '') !== '';
        })->once();
    }

    public function test_apply_verification_does_not_delete_identity_verification_events(): void
    {
        $user = User::factory()->create([
            'identity_validated' => false,
            'identity_validation_reject_reason' => 'name_mismatch',
        ]);

        $event = IdentityVerificationEvent::query()->create([
            'user_id' => $user->id,
            'method' => 'mercado_pago',
            'name' => 'failed',
            'reason' => 'name_mismatch',
            'attempt_id' => '33333333-3333-3333-3333-333333333333',
            'created_at' => now(),
        ]);

        ManualIdentityValidation::create([
            'user_id' => $user->id,
            'paid' => true,
            'paid_at' => now(),
            'submitted_at' => now(),
            'review_status' => ManualIdentityValidation::REVIEW_STATUS_REJECTED,
        ]);

        MercadoPagoRejectedValidation::create([
            'user_id' => $user->id,
            'reject_reason' => 'name_mismatch',
            'mp_payload' => ['first_name' => 'Jane'],
        ]);

        app(UserIdentityVerificationSuccessService::class)->applyVerification($user, 'mercado_pago');

        $this->assertDatabaseHas('identity_verification_events', ['id' => $event->id]);
        $this->assertSame(1, IdentityVerificationEvent::query()->where('user_id', $user->id)->count());
    }

    public function test_recorder_returns_null_instead_of_throwing_on_insert_failure(): void
    {
        $result = app(IdentityVerificationEventRecorder::class)->record([
            'user_id' => 9_999_999,
            'method' => 'mercado_pago',
            'name' => 'failed',
            'reason' => 'oauth_cancelled',
        ]);

        $this->assertNull($result);
        $this->assertSame(0, IdentityVerificationEvent::query()->count());
    }
}
