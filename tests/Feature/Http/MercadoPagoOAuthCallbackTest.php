<?php

namespace Tests\Feature\Http;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use STS\Models\IdentityVerificationEvent;
use STS\Models\ManualIdentityValidation;
use STS\Models\MercadoPagoRejectedValidation;
use STS\Models\User;
use STS\Services\IdentityVerificationOutcome;
use Tests\TestCase;

class MercadoPagoOAuthCallbackTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        config([
            'services.mercadopago.oauth_frontend_redirect' => 'https://app.example',
            'services.mercadopago.client_id' => 'test-client',
            'services.mercadopago.client_secret' => 'test-secret',
            'services.mercadopago.oauth_redirect_uri' => 'https://api.example/callback',
            'services.mercadopago.oauth_pkce_enabled' => false,
        ]);
    }

    protected function tearDown(): void
    {
        Http::fake();
        parent::tearDown();
    }

    private function identityRedirect(string $result): string
    {
        return 'https://app.example/setting/identity-validation?result='.urlencode($result);
    }

    private function identityRedirectWith(string $result, array $params): string
    {
        $query = array_merge(['result' => $result], $params);

        return 'https://app.example/setting/identity-validation?'.http_build_query($query);
    }

    public function test_redirects_to_error_when_mp_error_query_is_present(): void
    {
        Log::spy();

        $this->get('/api/mercadopago/oauth/callback?error=access_denied&state=ignored')
            ->assertRedirect($this->identityRedirect('error'));

        $this->assertDatabaseHas('identity_verification_events', [
            'method' => IdentityVerificationOutcome::METHOD_MERCADO_PAGO,
            'name' => IdentityVerificationOutcome::NAME_FAILED,
            'reason' => IdentityVerificationOutcome::REASON_OAUTH_CANCELLED,
        ]);

        Log::shouldHaveReceived('warning')->withArgs(function (...$args): bool {
            return ($args[0] ?? null) === 'Identity verification mercado_pago failed reason=oauth_cancelled'
                && ($args[1]['mp_error'] ?? null) === 'access_denied'
                && ($args[1]['reason'] ?? null) === 'oauth_cancelled';
        })->once();
    }

    public function test_oauth_cancel_with_state_attaches_user_and_attempt(): void
    {
        $user = User::factory()->create(['nro_doc' => '30123456']);
        $attemptId = 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa';
        Cache::put('mp_oauth_state:cancel-state', [
            'user_id' => $user->id,
            'attempt_id' => $attemptId,
            'surface' => 'choice_cards',
        ], 600);

        Log::spy();

        $this->get('/api/mercadopago/oauth/callback?error=access_denied&state=cancel-state')
            ->assertRedirect($this->identityRedirect('error'));

        $this->assertDatabaseHas('identity_verification_events', [
            'user_id' => $user->id,
            'reason' => IdentityVerificationOutcome::REASON_OAUTH_CANCELLED,
            'attempt_id' => $attemptId,
            'surface' => 'choice_cards',
        ]);
        $this->assertNull(Cache::get('mp_oauth_state:cancel-state'));
    }

    public function test_redirects_to_error_when_mp_error_is_not_access_denied(): void
    {
        Log::spy();

        $this->get('/api/mercadopago/oauth/callback?error=server_error&state=ignored')
            ->assertRedirect($this->identityRedirect('error'));

        $this->assertDatabaseHas('identity_verification_events', [
            'reason' => IdentityVerificationOutcome::REASON_OAUTH_DENIED,
        ]);

        Log::shouldHaveReceived('warning')->withArgs(function (...$args): bool {
            return ($args[0] ?? null) === 'Identity verification mercado_pago failed reason=oauth_denied'
                && ($args[1]['mp_error'] ?? null) === 'server_error';
        })->once();
    }

    public function test_redirects_to_error_when_code_or_state_missing(): void
    {
        Log::spy();

        $this->get('/api/mercadopago/oauth/callback?code=only-code')
            ->assertRedirect($this->identityRedirect('error'));

        $this->assertDatabaseHas('identity_verification_events', [
            'reason' => IdentityVerificationOutcome::REASON_MISSING_CODE_OR_STATE,
        ]);

        Log::shouldHaveReceived('warning')->withArgs(function (...$args): bool {
            return ($args[0] ?? null) === 'Identity verification mercado_pago failed reason=missing_code_or_state'
                && ($args[1]['has_code'] ?? null) === true
                && ($args[1]['has_state'] ?? null) === false;
        })->once();

        Log::spy();

        $this->get('/api/mercadopago/oauth/callback?state=only-state')
            ->assertRedirect($this->identityRedirect('error'));

        Log::shouldHaveReceived('warning')->withArgs(function (...$args): bool {
            return ($args[0] ?? null) === 'Identity verification mercado_pago failed reason=missing_code_or_state'
                && ($args[1]['has_code'] ?? null) === false
                && ($args[1]['has_state'] ?? null) === true;
        })->once();
    }

    public function test_redirects_to_error_when_state_not_cached(): void
    {
        Log::spy();

        $this->get('/api/mercadopago/oauth/callback?code=auth-code&state=unknown-state')
            ->assertRedirect($this->identityRedirect('error'));

        $this->assertDatabaseHas('identity_verification_events', [
            'reason' => IdentityVerificationOutcome::REASON_INVALID_OR_EXPIRED_STATE,
        ]);

        Log::shouldHaveReceived('warning')->withArgs(function (...$args): bool {
            return ($args[0] ?? null) === 'Identity verification mercado_pago failed reason=invalid_or_expired_state'
                && ($args[1]['state'] ?? null) === 'unknown-state';
        })->once();
    }

    public function test_redirects_to_error_when_cached_payload_lacks_user_id(): void
    {
        Cache::put('mp_oauth_state:no-user', ['code_verifier' => 'x'], 600);

        Log::spy();

        $this->get('/api/mercadopago/oauth/callback?code=auth-code&state=no-user')
            ->assertRedirect($this->identityRedirect('error'));

        Log::shouldHaveReceived('warning')->withArgs(function (...$args): bool {
            return ($args[0] ?? null) === 'Identity verification mercado_pago failed reason=invalid_or_expired_state'
                && ($args[1]['state'] ?? null) === 'no-user';
        })->once();
    }

    public function test_redirects_to_error_when_cached_user_id_has_no_user_row(): void
    {
        $missingUserId = (int) (User::query()->max('id') ?? 0) + 50_000;
        Cache::put('mp_oauth_state:orphan-state', ['user_id' => $missingUserId], 600);

        Log::spy();

        $this->get('/api/mercadopago/oauth/callback?code=auth-code&state=orphan-state')
            ->assertRedirect($this->identityRedirect('error'));

        $this->assertDatabaseHas('identity_verification_events', [
            'user_id' => null,
            'reason' => IdentityVerificationOutcome::REASON_USER_NOT_FOUND,
        ]);

        Log::shouldHaveReceived('warning')->withArgs(function (...$args) use ($missingUserId): bool {
            return ($args[0] ?? null) === 'Identity verification mercado_pago failed reason=user_not_found'
                && (int) ($args[1]['missing_user_id'] ?? 0) === $missingUserId;
        })->once();
    }

    public function test_redirects_to_error_when_token_response_has_no_access_token(): void
    {
        $user = User::factory()->create(['nro_doc' => '30123456']);
        Cache::put('mp_oauth_state:no-token-state', ['user_id' => $user->id], 600);

        Http::fake([
            '*oauth/token*' => Http::response(['refresh_token' => 'x'], 200),
        ]);

        Log::spy();

        $this->get('/api/mercadopago/oauth/callback?code=auth-code&state=no-token-state')
            ->assertRedirect($this->identityRedirect('error'));

        $this->assertDatabaseHas('identity_verification_events', [
            'user_id' => $user->id,
            'reason' => IdentityVerificationOutcome::REASON_MISSING_ACCESS_TOKEN,
        ]);

        Log::shouldHaveReceived('warning')->withArgs(function (...$args) use ($user): bool {
            return ($args[0] ?? null) === 'Identity verification mercado_pago failed reason=missing_access_token'
                && (int) ($args[1]['user_id'] ?? 0) === $user->id;
        })->once();
    }

    public function test_redirects_to_error_when_token_exchange_http_fails(): void
    {
        $user = User::factory()->create(['nro_doc' => '30123456']);
        Cache::put('mp_oauth_state:bad-http-state', ['user_id' => $user->id], 600);

        Http::fake([
            '*oauth/token*' => Http::response(['message' => 'invalid_grant'], 400),
        ]);

        Log::spy();

        $this->get('/api/mercadopago/oauth/callback?code=bad-code&state=bad-http-state')
            ->assertRedirect($this->identityRedirect('error'));

        $this->assertDatabaseHas('identity_verification_events', [
            'user_id' => $user->id,
            'reason' => IdentityVerificationOutcome::REASON_TOKEN_EXCHANGE_FAILED,
        ]);

        Log::shouldHaveReceived('error')->withArgs(function (...$args): bool {
            return ($args[0] ?? null) === 'Identity verification mercado_pago failed reason=token_exchange_failed'
                && ($args[1]['http_status'] ?? null) === 400;
        })->once();

        Log::shouldNotHaveReceived('error', function (...$args): bool {
            $message = (string) ($args[0] ?? '');

            return $message === 'MercadoPago OAuth callback exception'
                || str_contains($message, 'callback exception');
        });
    }

    public function test_redirects_to_error_when_users_me_fails_after_token(): void
    {
        $user = User::factory()->create([
            'name' => 'Jane Doe',
            'nro_doc' => '30123456',
        ]);
        Cache::put('mp_oauth_state:me-fail-state', ['user_id' => $user->id], 600);

        Http::fake([
            '*oauth/token*' => Http::response(['access_token' => 'tok'], 200),
            '*users/me*' => Http::response(['message' => 'unauthorized'], 401),
        ]);

        Log::spy();

        $this->get('/api/mercadopago/oauth/callback?code=auth-code&state=me-fail-state')
            ->assertRedirect($this->identityRedirect('error'));

        $this->assertDatabaseHas('identity_verification_events', [
            'user_id' => $user->id,
            'reason' => IdentityVerificationOutcome::REASON_USERS_ME_FAILED,
        ]);

        Log::shouldHaveReceived('error')->withArgs(function (...$args): bool {
            return ($args[0] ?? null) === 'Identity verification mercado_pago failed reason=users_me_failed'
                && ($args[1]['http_status'] ?? null) === 401;
        })->once();

        Log::shouldNotHaveReceived('error', function (...$args): bool {
            return ($args[0] ?? null) === 'MercadoPago OAuth callback exception';
        });
    }

    public function test_redirects_to_missing_identification_when_users_me_has_no_identification(): void
    {
        $user = User::factory()->create([
            'name' => 'Jane Doe',
            'nro_doc' => '30123456',
        ]);
        Cache::put('mp_oauth_state:no-id-state', ['user_id' => $user->id], 600);

        $me = [
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'company' => ['identification' => '20123456789'],
        ];

        Http::fake([
            '*oauth/token*' => Http::response(['access_token' => 'tok'], 200),
            '*users/me*' => Http::response($me, 200),
        ]);

        Log::spy();

        $this->get('/api/mercadopago/oauth/callback?code=auth-code&state=no-id-state')
            ->assertRedirect($this->identityRedirect('missing_identification'));

        $this->assertDatabaseHas('identity_verification_events', [
            'user_id' => $user->id,
            'reason' => IdentityVerificationOutcome::REASON_MISSING_IDENTIFICATION,
        ]);

        Log::shouldHaveReceived('warning')->withArgs(function (...$args) use ($user): bool {
            $context = $args[1] ?? [];

            return ($args[0] ?? null) === 'Identity verification mercado_pago failed reason=missing_identification'
                && (int) ($context['user_id'] ?? 0) === $user->id
                && ! array_key_exists('mp_payload', $context);
        })->once();
    }

    public function test_redirects_name_mismatch_and_records_rejection(): void
    {
        $user = User::factory()->create([
            'name' => 'Jane Doe',
            'nro_doc' => '30123456',
            'identity_validated' => false,
        ]);
        Cache::put('mp_oauth_state:name-state', ['user_id' => $user->id], 600);

        Http::fake([
            '*oauth/token*' => Http::response(['access_token' => 'tok'], 200),
            '*users/me*' => Http::response([
                'first_name' => 'Other',
                'last_name' => 'Person',
                'identification' => ['type' => 'DNI', 'number' => '30123456'],
            ], 200),
        ]);

        $this->get('/api/mercadopago/oauth/callback?code=auth-code&state=name-state')
            ->assertRedirect($this->identityRedirectWith('name_mismatch', [
                'user_name' => 'Jane Doe',
                'mp_name' => 'Other Person',
            ]));

        $user->refresh();
        $this->assertFalse($user->identity_validated);
        $this->assertSame('name_mismatch', $user->identity_validation_reject_reason);

        $this->assertDatabaseHas('mercado_pago_rejected_validations', [
            'user_id' => $user->id,
            'reject_reason' => 'name_mismatch',
        ]);

        $this->assertDatabaseHas('identity_verification_events', [
            'user_id' => $user->id,
            'method' => 'mercado_pago',
            'name' => 'failed',
            'reason' => 'name_mismatch',
        ]);
    }

    public function test_redirects_name_mismatch_when_local_name_is_empty(): void
    {
        $user = User::factory()->create([
            'name' => '',
            'nro_doc' => '30123456',
            'identity_validated' => false,
        ]);
        Cache::put('mp_oauth_state:name-empty-state', ['user_id' => $user->id], 600);

        Http::fake([
            '*oauth/token*' => Http::response(['access_token' => 'tok'], 200),
            '*users/me*' => Http::response([
                'first_name' => 'Jane',
                'last_name' => 'Doe',
                'identification' => ['type' => 'DNI', 'number' => '30123456'],
            ], 200),
        ]);

        $this->get('/api/mercadopago/oauth/callback?code=auth-code&state=name-empty-state')
            ->assertRedirect($this->identityRedirectWith('name_mismatch', [
                'user_name' => '',
                'mp_name' => 'Jane Doe',
            ]));
    }

    public function test_redirects_dni_mismatch_and_records_rejection(): void
    {
        $user = User::factory()->create([
            'name' => 'Jane Doe',
            'nro_doc' => '30123456',
            'identity_validated' => false,
        ]);
        Cache::put('mp_oauth_state:dni-state', ['user_id' => $user->id], 600);

        Http::fake([
            '*oauth/token*' => Http::response(['access_token' => 'tok'], 200),
            '*users/me*' => Http::response([
                'first_name' => 'Jane',
                'last_name' => 'Doe',
                'identification' => ['type' => 'DNI', 'number' => '30999999'],
            ], 200),
        ]);

        $this->get('/api/mercadopago/oauth/callback?code=auth-code&state=dni-state')
            ->assertRedirect($this->identityRedirectWith('dni_mismatch', [
                'user_dni' => '30123456',
                'mp_dni' => '30999999',
            ]));

        $user->refresh();
        $this->assertFalse($user->identity_validated);
        $this->assertSame('dni_mismatch', $user->identity_validation_reject_reason);

        $this->assertDatabaseHas('mercado_pago_rejected_validations', [
            'user_id' => $user->id,
            'reject_reason' => 'dni_mismatch',
        ]);

        $this->assertDatabaseHas('identity_verification_events', [
            'user_id' => $user->id,
            'name' => 'failed',
            'reason' => 'dni_mismatch',
        ]);
    }

    public function test_redirects_success_and_sets_identity_when_name_and_dni_match(): void
    {
        $user = User::factory()->create([
            'name' => 'Jane Doe',
            'nro_doc' => '30.123.456',
            'identity_validated' => false,
        ]);
        Cache::put('mp_oauth_state:ok-state', ['user_id' => $user->id], 600);

        Http::fake([
            '*oauth/token*' => Http::response(['access_token' => 'tok'], 200),
            '*users/me*' => Http::response([
                'first_name' => 'Jane',
                'last_name' => 'Doe',
                'identification' => ['type' => 'DNI', 'number' => '30123456'],
            ], 200),
        ]);

        $this->get('/api/mercadopago/oauth/callback?code=auth-code&state=ok-state')
            ->assertRedirect($this->identityRedirect('success'));

        $user->refresh();
        $this->assertTrue($user->identity_validated);
        $this->assertSame('mercado_pago', $user->identity_validation_type);
        $this->assertNull($user->identity_validation_reject_reason);

        $this->assertSame(0, MercadoPagoRejectedValidation::query()->where('user_id', $user->id)->count());

        $this->assertDatabaseHas('identity_verification_events', [
            'user_id' => $user->id,
            'name' => 'succeeded',
            'method' => 'mercado_pago',
        ]);
        $this->assertSame(1, IdentityVerificationEvent::query()->where('user_id', $user->id)->count());
    }

    public function test_success_closes_open_manual_identity_validations_for_the_user(): void
    {
        $user = User::factory()->create([
            'name' => 'Jane Doe',
            'nro_doc' => '30.123.456',
            'identity_validated' => false,
        ]);
        $otherUser = User::factory()->create();
        Cache::put('mp_oauth_state:close-manual-state', ['user_id' => $user->id], 600);

        $openManual = ManualIdentityValidation::create([
            'user_id' => $user->id,
            'paid' => true,
            'paid_at' => now(),
            'submitted_at' => now(),
            'review_status' => ManualIdentityValidation::REVIEW_STATUS_PENDING,
        ]);
        $otherUserManual = ManualIdentityValidation::create([
            'user_id' => $otherUser->id,
            'paid' => true,
            'paid_at' => now(),
            'submitted_at' => now(),
            'review_status' => ManualIdentityValidation::REVIEW_STATUS_PENDING,
        ]);

        Http::fake([
            '*oauth/token*' => Http::response(['access_token' => 'tok'], 200),
            '*users/me*' => Http::response([
                'first_name' => 'Jane',
                'last_name' => 'Doe',
                'identification' => ['type' => 'DNI', 'number' => '30123456'],
            ], 200),
        ]);

        $this->get('/api/mercadopago/oauth/callback?code=auth-code&state=close-manual-state')
            ->assertRedirect($this->identityRedirect('success'));

        $this->assertTrue($user->fresh()->identity_validated);
        $this->assertSame(ManualIdentityValidation::REVIEW_STATUS_CLOSED, $openManual->fresh()->review_status);
        $this->assertSame(ManualIdentityValidation::REVIEW_STATUS_PENDING, $otherUserManual->fresh()->review_status);
    }

    public function test_success_clears_prior_manual_rejection_state(): void
    {
        $user = User::factory()->create([
            'name' => 'Jane Doe',
            'nro_doc' => '30.123.456',
            'identity_validated' => false,
            'identity_validation_rejected_at' => now()->subDay(),
            'identity_validation_reject_reason' => 'name_mismatch',
        ]);
        Cache::put('mp_oauth_state:clear-rejection-state', ['user_id' => $user->id], 600);

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

        Http::fake([
            '*oauth/token*' => Http::response(['access_token' => 'tok'], 200),
            '*users/me*' => Http::response([
                'first_name' => 'Jane',
                'last_name' => 'Doe',
                'identification' => ['type' => 'DNI', 'number' => '30123456'],
            ], 200),
        ]);

        $this->get('/api/mercadopago/oauth/callback?code=auth-code&state=clear-rejection-state')
            ->assertRedirect($this->identityRedirect('success'));

        $user->refresh();
        $this->assertTrue($user->identity_validated);
        $this->assertNull($user->identity_validation_rejected_at);
        $this->assertNull($user->identity_validation_reject_reason);
        $this->assertDatabaseMissing('manual_identity_validations', ['id' => $rejectedManual->id]);
        $this->assertSame(0, MercadoPagoRejectedValidation::query()->where('user_id', $user->id)->count());
        $this->assertDatabaseHas('identity_verification_events', [
            'user_id' => $user->id,
            'name' => 'succeeded',
        ]);
    }

    public function test_resolves_user_when_cached_user_id_is_numeric_string(): void
    {
        $user = User::factory()->create([
            'name' => 'Jane Doe',
            'nro_doc' => '30123456',
        ]);
        Cache::put('mp_oauth_state:string-id-state', ['user_id' => (string) $user->id], 600);

        Http::fake([
            '*oauth/token*' => Http::response(['access_token' => 'tok'], 200),
            '*users/me*' => Http::response([
                'first_name' => 'Jane',
                'last_name' => 'Doe',
                'identification' => ['type' => 'DNI', 'number' => '30123456'],
            ], 200),
        ]);

        $this->get('/api/mercadopago/oauth/callback?code=auth-code&state=string-id-state')
            ->assertRedirect($this->identityRedirect('success'));

        $this->assertTrue($user->fresh()->identity_validated);
    }

    public function test_redirects_both_mismatch_when_name_and_dni_do_not_match(): void
    {
        $user = User::factory()->create([
            'name' => 'Jane Doe',
            'nro_doc' => '30123456',
            'identity_validated' => false,
        ]);
        Cache::put('mp_oauth_state:both-state', ['user_id' => $user->id], 600);

        Http::fake([
            '*oauth/token*' => Http::response(['access_token' => 'tok'], 200),
            '*users/me*' => Http::response([
                'first_name' => 'Other',
                'last_name' => 'Person',
                'identification' => ['type' => 'DNI', 'number' => '30999999'],
            ], 200),
        ]);

        $this->get('/api/mercadopago/oauth/callback?code=auth-code&state=both-state')
            ->assertRedirect($this->identityRedirectWith('both_mismatch', [
                'user_name' => 'Jane Doe',
                'mp_name' => 'Other Person',
                'user_dni' => '30123456',
                'mp_dni' => '30999999',
            ]));

        $this->assertDatabaseHas('identity_verification_events', [
            'user_id' => $user->id,
            'reason' => 'both_mismatch',
        ]);
    }
}
