<?php

namespace Tests\Feature\Http;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use STS\Models\MercadoPagoRejectedValidation;
use STS\Models\User;
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

        Log::shouldHaveReceived('info')->withArgs(function (string $message, array $context): bool {
            return $message === 'MercadoPago OAuth callback request'
                && ($context['request']['error'] ?? null) === 'access_denied'
                && ($context['request']['state'] ?? null) === 'ignored';
        })->once();

        Log::shouldHaveReceived('warning')->withArgs(function (...$args): bool {
            return ($args[0] ?? null) === 'MercadoPago OAuth callback: MP error param'
                && ($args[1]['error'] ?? null) === 'access_denied';
        })->once();
    }

    public function test_redirects_to_error_when_code_or_state_missing(): void
    {
        Log::spy();

        $this->get('/api/mercadopago/oauth/callback?code=only-code')
            ->assertRedirect($this->identityRedirect('error'));

        Log::shouldHaveReceived('warning')->withArgs(function (...$args): bool {
            return ($args[0] ?? null) === 'MercadoPago OAuth callback: missing code or state'
                && ($args[1]['has_code'] ?? null) === true
                && ($args[1]['has_state'] ?? null) === false;
        })->once();

        Log::spy();

        $this->get('/api/mercadopago/oauth/callback?state=only-state')
            ->assertRedirect($this->identityRedirect('error'));

        Log::shouldHaveReceived('warning')->withArgs(function (...$args): bool {
            return ($args[0] ?? null) === 'MercadoPago OAuth callback: missing code or state'
                && ($args[1]['has_code'] ?? null) === false
                && ($args[1]['has_state'] ?? null) === true;
        })->once();
    }

    public function test_redirects_to_error_when_state_not_cached(): void
    {
        Log::spy();

        $this->get('/api/mercadopago/oauth/callback?code=auth-code&state=unknown-state')
            ->assertRedirect($this->identityRedirect('error'));

        Log::shouldHaveReceived('warning')->withArgs(function (...$args): bool {
            return ($args[0] ?? null) === 'MercadoPago OAuth callback: invalid or expired state'
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
            return ($args[0] ?? null) === 'MercadoPago OAuth callback: invalid or expired state'
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

        Log::shouldHaveReceived('warning')->withArgs(function (...$args) use ($missingUserId): bool {
            return ($args[0] ?? null) === 'MercadoPago OAuth callback: user not found'
                && (int) ($args[1]['user_id'] ?? 0) === $missingUserId;
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

        Log::shouldHaveReceived('warning')->withArgs(function (...$args) use ($user): bool {
            return ($args[0] ?? null) === 'MercadoPago OAuth callback: no access_token in token response'
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

        Log::shouldHaveReceived('error')->withArgs(function (...$args): bool {
            return ($args[0] ?? null) === 'MercadoPago OAuth callback exception'
                && is_string($args[1]['message'] ?? null)
                && $args[1]['message'] !== '';
        })->once();
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

        Log::shouldHaveReceived('error')->withArgs(function (...$args): bool {
            return ($args[0] ?? null) === 'MercadoPago OAuth callback exception'
                && is_string($args[1]['message'] ?? null)
                && ($args[1]['message'] ?? '') !== '';
        })->once();
    }

    public function test_redirects_to_error_when_users_me_has_no_identification(): void
    {
        $user = User::factory()->create([
            'name' => 'Jane Doe',
            'nro_doc' => '30123456',
        ]);
        Cache::put('mp_oauth_state:no-id-state', ['user_id' => $user->id], 600);

        Http::fake([
            '*oauth/token*' => Http::response(['access_token' => 'tok'], 200),
            '*users/me*' => Http::response([
                'first_name' => 'Jane',
                'last_name' => 'Doe',
            ], 200),
        ]);

        Log::spy();

        $this->get('/api/mercadopago/oauth/callback?code=auth-code&state=no-id-state')
            ->assertRedirect($this->identityRedirect('error'));

        Log::shouldHaveReceived('warning')->withArgs(function (...$args) use ($user): bool {
            return ($args[0] ?? null) === 'MercadoPago OAuth callback: no identification in users/me'
                && (int) ($args[1]['user_id'] ?? 0) === $user->id;
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

        Log::spy();

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

        Log::shouldHaveReceived('info')->withArgs(function (...$args) use ($user): bool {
            return ($args[0] ?? null) === 'MercadoPago OAuth users/me response'
                && (int) ($args[1]['user_id'] ?? 0) === $user->id
                && is_array($args[1]['me'] ?? null);
        })->once();

        Log::shouldHaveReceived('info')->withArgs(function (...$args) use ($user): bool {
            return ($args[0] ?? null) === 'MercadoPago OAuth callback: mismatch'
                && (int) ($args[1]['user_id'] ?? 0) === $user->id
                && ($args[1]['reject_reason'] ?? null) === 'name_mismatch';
        })->once();
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

        Log::spy();

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

        Log::shouldHaveReceived('info')->withArgs(function (...$args) use ($user): bool {
            return ($args[0] ?? null) === 'MercadoPago OAuth callback: mismatch'
                && (int) ($args[1]['user_id'] ?? 0) === $user->id
                && ($args[1]['reject_reason'] ?? null) === 'dni_mismatch'
                && ($args[1]['user_dni_normalized'] ?? null) === '30123456'
                && ($args[1]['mp_dni_normalized'] ?? null) === '30999999';
        })->once();
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

        Log::spy();

        $this->get('/api/mercadopago/oauth/callback?code=auth-code&state=ok-state')
            ->assertRedirect($this->identityRedirect('success'));

        $user->refresh();
        $this->assertTrue($user->identity_validated);
        $this->assertSame('mercado_pago', $user->identity_validation_type);
        $this->assertNull($user->identity_validation_reject_reason);

        $this->assertSame(0, MercadoPagoRejectedValidation::query()->where('user_id', $user->id)->count());

        Log::shouldHaveReceived('info')->withArgs(function (...$args) use ($user): bool {
            return ($args[0] ?? null) === 'MercadoPago OAuth callback: success'
                && (int) ($args[1]['user_id'] ?? 0) === $user->id;
        })->once();
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
    }
}
