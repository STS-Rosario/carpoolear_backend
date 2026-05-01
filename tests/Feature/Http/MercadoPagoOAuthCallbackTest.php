<?php

namespace Tests\Feature\Http;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
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

    public function test_redirects_to_error_when_mp_error_query_is_present(): void
    {
        $this->get('/api/mercadopago/oauth/callback?error=access_denied&state=ignored')
            ->assertRedirect($this->identityRedirect('error'));
    }

    public function test_redirects_to_error_when_code_or_state_missing(): void
    {
        $this->get('/api/mercadopago/oauth/callback?code=only-code')
            ->assertRedirect($this->identityRedirect('error'));

        $this->get('/api/mercadopago/oauth/callback?state=only-state')
            ->assertRedirect($this->identityRedirect('error'));
    }

    public function test_redirects_to_error_when_state_not_cached(): void
    {
        $this->get('/api/mercadopago/oauth/callback?code=auth-code&state=unknown-state')
            ->assertRedirect($this->identityRedirect('error'));
    }

    public function test_redirects_to_error_when_cached_user_id_has_no_user_row(): void
    {
        $missingUserId = (int) (User::query()->max('id') ?? 0) + 50_000;
        Cache::put('mp_oauth_state:orphan-state', ['user_id' => $missingUserId], 600);

        $this->get('/api/mercadopago/oauth/callback?code=auth-code&state=orphan-state')
            ->assertRedirect($this->identityRedirect('error'));
    }

    public function test_redirects_to_error_when_token_response_has_no_access_token(): void
    {
        $user = User::factory()->create(['nro_doc' => '30123456']);
        Cache::put('mp_oauth_state:no-token-state', ['user_id' => $user->id], 600);

        Http::fake([
            '*oauth/token*' => Http::response(['refresh_token' => 'x'], 200),
        ]);

        $this->get('/api/mercadopago/oauth/callback?code=auth-code&state=no-token-state')
            ->assertRedirect($this->identityRedirect('error'));
    }

    public function test_redirects_to_error_when_token_exchange_http_fails(): void
    {
        $user = User::factory()->create(['nro_doc' => '30123456']);
        Cache::put('mp_oauth_state:bad-http-state', ['user_id' => $user->id], 600);

        Http::fake([
            '*oauth/token*' => Http::response(['message' => 'invalid_grant'], 400),
        ]);

        $this->get('/api/mercadopago/oauth/callback?code=bad-code&state=bad-http-state')
            ->assertRedirect($this->identityRedirect('error'));
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
            ->assertRedirect($this->identityRedirect('name_mismatch'));

        $user->refresh();
        $this->assertFalse($user->identity_validated);
        $this->assertSame('name_mismatch', $user->identity_validation_reject_reason);

        $this->assertDatabaseHas('mercado_pago_rejected_validations', [
            'user_id' => $user->id,
            'reject_reason' => 'name_mismatch',
        ]);
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
            ->assertRedirect($this->identityRedirect('dni_mismatch'));

        $user->refresh();
        $this->assertFalse($user->identity_validated);
        $this->assertSame('dni_mismatch', $user->identity_validation_reject_reason);

        $this->assertDatabaseHas('mercado_pago_rejected_validations', [
            'user_id' => $user->id,
            'reject_reason' => 'dni_mismatch',
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
    }
}
