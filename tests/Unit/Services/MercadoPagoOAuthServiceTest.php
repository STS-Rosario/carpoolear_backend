<?php

namespace Tests\Unit\Services;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Mockery;
use ReflectionMethod;
use STS\Services\MercadoPagoOAuthService;
use Tests\TestCase;

class MercadoPagoOAuthServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_get_authorization_url_without_pkce_includes_required_query_params_and_platform_id(): void
    {
        $this->configureMercadoPagoService(pkce: false);

        $svc = new MercadoPagoOAuthService;
        $url = $svc->getAuthorizationUrl('state-xyz');

        $this->assertIsString($url);
        $this->assertStringStartsWith('https://auth.mercadopago.com/authorization?', $url);
        parse_str((string) parse_url($url, PHP_URL_QUERY), $q);
        $this->assertSame('cid', $q['client_id']);
        $this->assertSame('code', $q['response_type']);
        $this->assertSame('state-xyz', $q['state']);
        $this->assertSame('https://app.test/oauth/callback', $q['redirect_uri']);
        $this->assertSame('mp', $q['platform_id']);
    }

    public function test_get_authorization_url_trims_trailing_slash_on_auth_base(): void
    {
        Config::set('services.mercadopago.client_id', 'x');
        Config::set('services.mercadopago.client_secret', 's');
        Config::set('services.mercadopago.oauth_redirect_uri', 'https://cb');
        Config::set('services.mercadopago.oauth_frontend_redirect', 'https://front.test');
        Config::set('services.mercadopago.oauth_pkce_enabled', false);
        Config::set('services.mercadopago.oauth_auth_url_base', 'https://auth.mercadopago.com///');

        $svc = new MercadoPagoOAuthService;
        $url = $svc->getAuthorizationUrl('s');

        $this->assertStringStartsWith('https://auth.mercadopago.com/authorization?', $url);
    }

    public function test_get_authorization_url_with_pkce_returns_array_with_url_verifier_and_s256_challenge(): void
    {
        $this->configureMercadoPagoService(pkce: true);

        $svc = new MercadoPagoOAuthService;
        $out = $svc->getAuthorizationUrl('pkce-state');

        $this->assertIsArray($out);
        $this->assertArrayHasKey('authorization_url', $out);
        $this->assertArrayHasKey('code_verifier', $out);
        $this->assertIsString($out['authorization_url']);
        $this->assertIsString($out['code_verifier']);

        parse_str((string) parse_url($out['authorization_url'], PHP_URL_QUERY), $q);
        $this->assertSame('S256', $q['code_challenge_method'] ?? null);
        $this->assertArrayHasKey('code_challenge', $q);
        $this->assertArrayNotHasKey('platform_id', $q);

        $this->assertMatchesRegularExpression('/^[A-Za-z0-9._~-]{43,64}$/', $out['code_verifier']);
        $expectedChallenge = rtrim(strtr(base64_encode(hash('sha256', $out['code_verifier'], true)), '+/', '-_'), '=');
        $this->assertSame($expectedChallenge, $q['code_challenge']);
    }

    public function test_generate_code_challenge_uses_binary_sha256(): void
    {
        $svc = new MercadoPagoOAuthService;
        $m = new ReflectionMethod(MercadoPagoOAuthService::class, 'generateCodeChallenge');
        $m->setAccessible(true);
        $challenge = $m->invoke($svc, 'plain-verifier');
        $expected = rtrim(strtr(base64_encode(hash('sha256', 'plain-verifier', true)), '+/', '-_'), '=');
        $this->assertSame($expected, $challenge);
    }

    public function test_exchange_code_for_token_posts_expected_body_and_logs_on_failure(): void
    {
        $this->configureMercadoPagoService(pkce: false);

        Http::fake([
            'https://api.mercadopago.com/oauth/token' => Http::response(['error' => 'invalid_grant'], 400),
        ]);

        Log::shouldReceive('error')
            ->once()
            ->with(
                'MercadoPago OAuth token exchange failed',
                Mockery::on(function ($context): bool {
                    return is_array($context)
                        && ($context['status'] ?? null) === 400
                        && str_contains((string) ($context['body'] ?? ''), 'invalid_grant');
                })
            );

        $svc = new MercadoPagoOAuthService;

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Failed to exchange code for token');

        $svc->exchangeCodeForToken('auth-code', null);
    }

    public function test_exchange_code_for_token_includes_code_verifier_when_non_empty(): void
    {
        $this->configureMercadoPagoService(pkce: false);

        Http::fake(function (\Illuminate\Http\Client\Request $request) {
            $body = $request->data();
            if (($body['code_verifier'] ?? null) !== 'verifier-secret') {
                return Http::response(['error' => 'bad'], 400);
            }

            return Http::response(['access_token' => 'tok'], 200);
        });

        $svc = new MercadoPagoOAuthService;
        $payload = $svc->exchangeCodeForToken('c', 'verifier-secret');

        $this->assertSame('tok', $payload['access_token']);
    }

    public function test_exchange_code_for_token_omits_code_verifier_when_null(): void
    {
        $this->configureMercadoPagoService(pkce: false);

        Http::fake(function (\Illuminate\Http\Client\Request $request) {
            $body = $request->data();
            if (array_key_exists('code_verifier', $body)) {
                return Http::response(['error' => 'unexpected_verifier'], 400);
            }

            return Http::response(['access_token' => 'ok-null'], 200);
        });

        $svc = new MercadoPagoOAuthService;
        $this->assertSame('ok-null', $svc->exchangeCodeForToken('c', null)['access_token']);
    }

    public function test_exchange_code_for_token_omits_code_verifier_when_empty_string(): void
    {
        $this->configureMercadoPagoService(pkce: false);

        Http::fake(function (\Illuminate\Http\Client\Request $request) {
            $body = $request->data();
            if (array_key_exists('code_verifier', $body)) {
                return Http::response(['error' => 'unexpected_verifier'], 400);
            }

            return Http::response(['access_token' => 'ok-empty'], 200);
        });

        $svc = new MercadoPagoOAuthService;
        $this->assertSame('ok-empty', $svc->exchangeCodeForToken('c', '')['access_token']);
    }

    public function test_get_user_me_logs_and_throws_on_non_success(): void
    {
        $this->configureMercadoPagoService(pkce: false);

        Http::fake([
            'https://api.mercadopago.com/users/me' => Http::response('gone', 410),
        ]);

        Log::shouldReceive('error')
            ->once()
            ->with(
                'MercadoPago users/me failed',
                Mockery::on(function ($context): bool {
                    return is_array($context)
                        && ($context['status'] ?? null) === 410
                        && ($context['body'] ?? '') === 'gone';
                })
            );

        $svc = new MercadoPagoOAuthService;
        $this->expectException(\Exception::class);
        $svc->getUserMe('token-abc');
    }

    public function test_normalize_dni_strips_non_digits_and_handles_empty(): void
    {
        $this->assertSame('', MercadoPagoOAuthService::normalizeDni(null));
        $this->assertSame('', MercadoPagoOAuthService::normalizeDni(''));
        $this->assertSame('12345678', MercadoPagoOAuthService::normalizeDni('12.345.678'));
    }

    public function test_extract_dni_for_comparison_strips_cuil_wrapper(): void
    {
        $this->assertSame(
            '123456789',
            MercadoPagoOAuthService::extractDniForComparison([
                'type' => 'CUIL',
                'number' => '201234567896',
            ])
        );
    }

    public function test_normalize_name_for_comparison_lowercases_and_strips_accents(): void
    {
        $this->assertSame('gonzalez', MercadoPagoOAuthService::normalizeNameForComparison('  González  '));
    }

    public function test_name_matches_accepts_subset_of_multi_word_first_name(): void
    {
        $me = [
            'first_name' => 'Santiago Ignacio',
            'last_name' => 'Caso',
        ];
        $this->assertTrue(MercadoPagoOAuthService::nameMatches($me, 'Santiago Caso'));
        $this->assertFalse(MercadoPagoOAuthService::nameMatches($me, 'Pedro Caso'));
        $this->assertFalse(MercadoPagoOAuthService::nameMatches(['first_name' => '', 'last_name' => 'X'], 'A B'));
    }

    public function test_filter_me_payload_for_storage_keeps_only_allowlisted_keys(): void
    {
        $me = [
            'email' => 'a@b.c',
            'noise' => 'drop',
            'first_name' => 'F',
            'identification' => ['type' => 'DNI', 'number' => '1'],
        ];
        $this->assertSame(
            ['email' => 'a@b.c', 'first_name' => 'F', 'identification' => ['type' => 'DNI', 'number' => '1']],
            MercadoPagoOAuthService::filterMePayloadForStorage($me)
        );
    }

    public function test_get_frontend_redirect_url_appends_encoded_result(): void
    {
        Config::set('services.mercadopago.client_id', 'x');
        Config::set('services.mercadopago.client_secret', 'y');
        Config::set('services.mercadopago.oauth_redirect_uri', 'z');
        Config::set('services.mercadopago.oauth_frontend_redirect', 'https://spa.example');
        Config::set('services.mercadopago.oauth_pkce_enabled', false);

        $svc = new MercadoPagoOAuthService;
        $url = $svc->getFrontendRedirectUrl('ok');

        $this->assertSame('https://spa.example/setting/identity-validation?result='.rawurlencode('ok'), $url);
    }

    private function configureMercadoPagoService(bool $pkce): void
    {
        Config::set('services.mercadopago.client_id', 'cid');
        Config::set('services.mercadopago.client_secret', 'sec');
        Config::set('services.mercadopago.oauth_redirect_uri', 'https://app.test/oauth/callback');
        Config::set('services.mercadopago.oauth_frontend_redirect', 'https://front.test');
        Config::set('services.mercadopago.oauth_pkce_enabled', $pkce);
        Config::set('services.mercadopago.oauth_auth_url_base', 'https://auth.mercadopago.com');
    }
}
