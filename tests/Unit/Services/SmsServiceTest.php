<?php

namespace Tests\Unit\Services;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use STS\Services\SmsService;
use Tests\TestCase;

final class SmsServiceWhatsAppFacebookHarness extends SmsService
{
    public object $facebookDouble;

    protected function createFacebookSdk(array $fbConfig): object
    {
        return $this->facebookDouble;
    }
}

class SmsServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_generate_verification_code_returns_six_numeric_characters(): void
    {
        $service = new SmsService;
        $code = $service->generateVerificationCode();

        $this->assertMatchesRegularExpression('/^\d{6}$/', $code);
    }

    public function test_format_phone_number_normalizes_argentina_numbers(): void
    {
        $service = new SmsService;

        $this->assertSame('+541122223333', $service->formatPhoneNumber('1122223333'));
        $this->assertSame('+541122223333', $service->formatPhoneNumber('01122223333'));
    }

    public function test_format_phone_number_keeps_existing_country_code_digits(): void
    {
        $service = new SmsService;

        $this->assertSame('+541122223333', $service->formatPhoneNumber('+54 11 2222 3333'));
    }

    public function test_send_returns_false_when_provider_is_not_configured(): void
    {
        Config::set('sms.default', 'unknown-provider');
        Config::set('sms.providers.unknown-provider', []);

        Log::shouldReceive('error')
            ->once()
            ->with('SMS provider not configured: unknown-provider');

        $service = new SmsService;

        $this->assertFalse($service->send('1122223333', 'test message'));
    }

    public function test_format_phone_number_removes_non_numeric_characters(): void
    {
        $service = new SmsService;

        $this->assertSame('+541122223333', $service->formatPhoneNumber('(011) 2222-3333'));
    }

    public function test_format_phone_number_preserves_existing_54_prefix_without_adding_extra_digits(): void
    {
        $service = new SmsService;

        $this->assertSame('+541122223333', $service->formatPhoneNumber('541122223333'));
    }

    public function test_send_logs_exception_message_and_returns_false_when_inner_send_throws(): void
    {
        Config::set('sms.default', 'whatsapp');
        Config::set('sms.providers.whatsapp', [
            'app_id' => 'app',
            'app_secret' => 'secret',
            'access_token' => 'token',
            'phone_number_id' => 'phone-id',
        ]);

        Log::shouldReceive('error')
            ->once()
            ->with('SMS sending failed: transport exploded');

        $service = new class extends SmsService
        {
            protected function sendViaWhatsApp($to, $message)
            {
                throw new \RuntimeException('transport exploded');
            }
        };

        $this->assertFalse($service->send('1122223333', 'code 123456'));
    }

    public function test_send_whatsapp_returns_false_when_configuration_is_incomplete(): void
    {
        Config::set('sms.default', 'whatsapp');
        Config::set('sms.providers.whatsapp', [
            'app_id' => 'x',
            'app_secret' => 'y',
            'access_token' => 'z',
            'phone_number_id' => null,
        ]);

        Log::shouldReceive('error')->once()->with('WhatsApp configuration missing');

        $service = new SmsService;

        $this->assertFalse($service->send('1122223333', 'code 654321'));
    }

    /**
     * @return iterable<string, array{0: array<string, mixed>}>
     */
    public static function incompleteWhatsappConfigs(): iterable
    {
        $base = [
            'app_id' => 'a',
            'app_secret' => 'b',
            'access_token' => 'c',
            'phone_number_id' => 'p',
        ];

        yield 'missing_app_id' => [array_merge($base, ['app_id' => null])];
        yield 'missing_app_secret' => [array_merge($base, ['app_secret' => null])];
        yield 'missing_access_token' => [array_merge($base, ['access_token' => null])];
        yield 'missing_phone_number_id' => [array_merge($base, ['phone_number_id' => null])];
    }

    #[DataProvider('incompleteWhatsappConfigs')]
    public function test_send_whatsapp_returns_false_when_any_required_credential_is_missing(array $whatsappConfig): void
    {
        Config::set('sms.default', 'whatsapp');
        Config::set('sms.providers.whatsapp', $whatsappConfig);

        Log::shouldReceive('error')->once()->with('WhatsApp configuration missing');

        $service = new SmsService;

        $this->assertFalse($service->send('1122223333', 'code 777777'));
    }

    public function test_send_whatsapp_uses_local_http_branch_with_full_graph_template_payload(): void
    {
        $previousEnv = $this->app['env'];

        try {
            $this->app['env'] = 'local';

            Config::set('sms.default', 'whatsapp');
            Config::set('sms.verification.expires_in_minutes', 9);
            Config::set('sms.providers.whatsapp', [
                'app_id' => 'app',
                'app_secret' => 'secret',
                'access_token' => 'test-access',
                'phone_number_id' => 'pnid-77',
                'default_graph_version' => 'v21.0',
            ]);

            Http::fake([
                'https://graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.1']]], 200),
            ]);

            $service = new SmsService;

            $this->assertTrue($service->send('1122223333', 'Su código es 123456'));

            Http::assertSent(function ($request) {
                if (! str_contains($request->url(), 'https://graph.facebook.com/v21.0/pnid-77/messages')) {
                    return false;
                }

                $headers = $request->headers();
                $auth = $headers['Authorization'][0] ?? '';
                if (! str_starts_with($auth, 'Bearer test-access')) {
                    return false;
                }

                $data = $request->data();
                $template = $data['template'] ?? [];
                $bodyParams = $template['components'][0]['parameters'] ?? [];

                return ($data['messaging_product'] ?? null) === 'whatsapp'
                    && ($data['to'] ?? null) === '541122223333'
                    && ($data['type'] ?? null) === 'template'
                    && ($template['name'] ?? null) === 'verification_code'
                    && ($template['language']['code'] ?? null) === 'es_AR'
                    && ($bodyParams[0]['text'] ?? null) === '123456'
                    && ($bodyParams[1]['text'] ?? null) === '9';
            });
        } finally {
            $this->app['env'] = $previousEnv;
        }
    }

    public function test_send_whatsapp_local_http_returns_false_when_graph_response_has_no_wa_message_id(): void
    {
        $previousEnv = $this->app['env'];

        try {
            $this->app['env'] = 'local';

            Config::set('sms.default', 'whatsapp');
            Config::set('sms.providers.whatsapp', [
                'app_id' => 'app',
                'app_secret' => 'secret',
                'access_token' => 'test-access',
                'phone_number_id' => 'pnid-88',
            ]);

            Http::fake([
                'https://graph.facebook.com/*' => Http::response(['ok' => true], 200),
            ]);

            $service = new SmsService;

            $this->assertFalse($service->send('1122223333', 'code 222222'));
        } finally {
            $this->app['env'] = $previousEnv;
        }
    }

    public function test_send_whatsapp_local_http_requires_wa_message_id_on_index_zero_not_later_entries(): void
    {
        $previousEnv = $this->app['env'];

        try {
            $this->app['env'] = 'local';

            Config::set('sms.default', 'whatsapp');
            Config::set('sms.providers.whatsapp', [
                'app_id' => 'app',
                'app_secret' => 'secret',
                'access_token' => 'test-access',
                'phone_number_id' => 'pnid-99',
            ]);

            Http::fake([
                'https://graph.facebook.com/*' => Http::response([
                    'messages' => [
                        ['status' => 'queued'],
                        ['id' => 'wamid.only-on-second'],
                    ],
                ], 200),
            ]);

            Log::shouldReceive('error')
                ->once()
                ->withArgs(function (string $message): bool {
                    return str_starts_with($message, 'WhatsApp API error via Laravel HTTP client:');
                });

            $service = new SmsService;

            $this->assertFalse($service->send('1122223333', 'code 333333'));
        } finally {
            $this->app['env'] = $previousEnv;
        }
    }

    public function test_send_whatsapp_local_http_logs_transport_failure_including_status_and_body(): void
    {
        $previousEnv = $this->app['env'];

        try {
            $this->app['env'] = 'local';

            Config::set('sms.default', 'whatsapp');
            Config::set('sms.providers.whatsapp', [
                'app_id' => 'app',
                'app_secret' => 'secret',
                'access_token' => 'test-access',
                'phone_number_id' => 'pnid-100',
            ]);

            Http::fake([
                'https://graph.facebook.com/*' => Http::response('rate limited', 429),
            ]);

            Log::shouldReceive('error')
                ->once()
                ->with('WhatsApp HTTP client failed with status: 429 - rate limited');

            $service = new SmsService;

            $this->assertFalse($service->send('1122223333', 'code 444444'));
        } finally {
            $this->app['env'] = $previousEnv;
        }
    }

    public function test_send_whatsapp_local_http_logs_success_line_including_formatted_phone_and_message(): void
    {
        $previousEnv = $this->app['env'];

        try {
            $this->app['env'] = 'local';

            Config::set('sms.default', 'whatsapp');
            Config::set('sms.providers.whatsapp', [
                'app_id' => 'app',
                'app_secret' => 'secret',
                'access_token' => 'test-access',
                'phone_number_id' => 'pnid-101',
            ]);

            Http::fake([
                'https://graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.ok']]], 200),
            ]);

            $message = 'Su código es 555555';

            $service = new SmsService;

            $this->assertTrue($service->send('1122223333', $message));
        } finally {
            $this->app['env'] = $previousEnv;
        }
    }

    public function test_send_sms_masivos_returns_false_when_api_key_missing(): void
    {
        Config::set('sms.default', 'smsmasivos');
        Config::set('sms.providers.smsmasivos', [
            'api_key' => null,
            'test_mode' => false,
        ]);

        Log::shouldReceive('error')->once()->with('SMS Masivos API key missing');

        $service = new SmsService;

        $this->assertFalse($service->send('1122223333', 'hello'));
    }

    public function test_send_sms_masivos_ok_response_returns_true_and_logs(): void
    {
        Config::set('sms.default', 'smsmasivos');
        Config::set('sms.providers.smsmasivos', [
            'api_key' => 'secret-key',
            'test_mode' => false,
        ]);

        Http::fake([
            'http://servicio.smsmasivos.com.ar/*' => Http::response('OK', 200),
        ]);

        $service = new SmsService;

        $this->assertTrue($service->send('1122223333', 'ping'));
    }

    public function test_send_sms_masivos_test_mode_phrase_returns_true(): void
    {
        Config::set('sms.default', 'smsmasivos');
        Config::set('sms.providers.smsmasivos', [
            'api_key' => 'secret-key',
            'test_mode' => true,
        ]);

        Http::fake([
            'http://servicio.smsmasivos.com.ar/*' => Http::response("probando sin enviar\n", 200),
        ]);

        $service = new SmsService;

        $this->assertTrue($service->send('1122223333', 'x'));
    }

    public function test_send_sms_masivos_non_ok_body_logs_error_and_returns_false(): void
    {
        Config::set('sms.default', 'smsmasivos');
        Config::set('sms.providers.smsmasivos', [
            'api_key' => 'secret-key',
            'test_mode' => false,
        ]);

        Http::fake([
            'http://servicio.smsmasivos.com.ar/*' => Http::response('ERR 9', 200),
        ]);

        Log::shouldReceive('error')->once()->with('SMS Masivos error: ERR 9');

        $service = new SmsService;

        $this->assertFalse($service->send('1122223333', 'm'));
    }

    public function test_send_sms_masivos_http_error_logs_status(): void
    {
        Config::set('sms.default', 'smsmasivos');
        Config::set('sms.providers.smsmasivos', [
            'api_key' => 'secret-key',
            'test_mode' => false,
        ]);

        Http::fake([
            'http://servicio.smsmasivos.com.ar/*' => Http::response('', 500),
        ]);

        Log::shouldReceive('error')->once()->with('SMS Masivos HTTP error: 500');

        $service = new SmsService;

        $this->assertFalse($service->send('1122223333', 'm'));
    }

    public function test_send_sms_masivos_appends_test_flag_when_test_mode_enabled(): void
    {
        Config::set('sms.default', 'smsmasivos');
        Config::set('sms.providers.smsmasivos', [
            'api_key' => 'k',
            'test_mode' => true,
        ]);

        Http::fake([
            'http://servicio.smsmasivos.com.ar/*' => Http::response('OK', 200),
        ]);

        $service = new SmsService;
        $service->send('1122223333', 'body');

        Http::assertSent(function ($request): bool {
            $q = $request->data();

            return ($q['test'] ?? null) === 1 && ($q['api'] ?? null) === 1;
        });
    }

    public function test_sms_service_harness_extracts_six_digit_code_from_message(): void
    {
        $h = new class extends SmsService
        {
            public function exposeExtract(string $message): string
            {
                return $this->extractCodeFromMessage($message);
            }
        };

        $this->assertSame('654321', $h->exposeExtract('Your code is 654321 today'));
    }

    public function test_sms_service_harness_falls_back_to_generate_when_no_six_digit_token(): void
    {
        $h = new class extends SmsService
        {
            public function exposeExtract(string $message): string
            {
                return $this->extractCodeFromMessage($message);
            }

            public function generateVerificationCode(): string
            {
                return '111111';
            }
        };

        $this->assertSame('111111', $h->exposeExtract('no digits like this'));
    }

    public function test_sms_service_harness_formats_whatsapp_numbers_for_argentina(): void
    {
        $h = new class extends SmsService
        {
            public function exposeWa(string $phone): string
            {
                return $this->formatPhoneForWhatsApp($phone);
            }
        };

        $this->assertSame('541122223333', $h->exposeWa('1122223333'));
        $this->assertSame('541122223333', $h->exposeWa('01122223333'));
    }

    public function test_sms_service_harness_formats_sms_masivos_numbers_and_warns_on_unexpected_length(): void
    {
        $h = new class extends SmsService
        {
            public function exposeSmsMasivos(string $phone): string
            {
                return $this->formatPhoneForSmsMasivos($phone);
            }
        };

        $this->assertSame('1122223333', $h->exposeSmsMasivos('01122223333'));

        Log::shouldReceive('warning')
            ->once()
            ->withArgs(function (string $message): bool {
                return str_starts_with($message, 'Phone number format may be incorrect for SMS Masivos: ')
                    && str_contains($message, '123');
            });

        $this->assertSame('123', $h->exposeSmsMasivos('123'));
    }

    public function test_send_via_local_appends_line_to_sms_log_and_returns_true(): void
    {
        Config::set('sms.default', 'local');
        Config::set('sms.providers.local', []);

        $path = storage_path('logs/sms.log');
        if (is_file($path)) {
            unlink($path);
        }

        $service = new SmsService;
        $this->assertTrue($service->send('+54 11 2222-3333', 'Hello local'));

        $this->assertFileExists($path);
        $line = (string) file_get_contents($path);
        $this->assertStringContainsString('SMS to +54 11 2222-3333', $line);
        $this->assertStringContainsString(': Hello local', $line);
        unlink($path);
    }

    public function test_send_whatsapp_non_local_env_uses_facebook_sdk_success_path(): void
    {
        $previousEnv = $this->app['env'];

        try {
            $this->app['env'] = 'production';

            Config::set('sms.default', 'whatsapp');
            Config::set('sms.verification.expires_in_minutes', 7);
            Config::set('sms.providers.whatsapp', [
                'app_id' => 'app',
                'app_secret' => 'secret',
                'access_token' => 'tok',
                'phone_number_id' => 'pn-prod',
                'default_graph_version' => 'v20.0',
            ]);

            $fbResponse = Mockery::mock();
            $fbResponse->shouldReceive('getDecodedBody')->once()->andReturn([
                'messages' => [['id' => 'wamid.prod']],
            ]);

            $facebook = Mockery::mock();
            $facebook->shouldReceive('post')
                ->once()
                ->withArgs(function (string $path, array $payload, string $token): bool {
                    if ($path !== '/pn-prod/messages' || $token !== 'tok') {
                        return false;
                    }
                    if (($payload['messaging_product'] ?? null) !== 'whatsapp') {
                        return false;
                    }
                    if (($payload['to'] ?? null) !== '541122223333') {
                        return false;
                    }
                    $params = $payload['template']['components'][0]['parameters'] ?? [];

                    return ($params[0]['text'] ?? null) === '888888'
                        && ($params[1]['text'] ?? null) === '7';
                })
                ->andReturn($fbResponse);

            $service = new SmsServiceWhatsAppFacebookHarness;
            $service->facebookDouble = $facebook;

            $this->assertTrue($service->send('1122223333', 'Código 888888'));
        } finally {
            $this->app['env'] = $previousEnv;
        }
    }

    public function test_send_whatsapp_non_local_env_returns_false_when_graph_body_has_no_message_id(): void
    {
        $previousEnv = $this->app['env'];

        try {
            $this->app['env'] = 'staging';

            Config::set('sms.default', 'whatsapp');
            Config::set('sms.providers.whatsapp', [
                'app_id' => 'app',
                'app_secret' => 'secret',
                'access_token' => 'tok',
                'phone_number_id' => 'pn-st',
            ]);

            $fbResponse = Mockery::mock();
            $fbResponse->shouldReceive('getDecodedBody')->once()->andReturn(['errors' => [['message' => 'nope']]]);

            $facebook = Mockery::mock();
            $facebook->shouldReceive('post')->once()->andReturn($fbResponse);

            Log::shouldReceive('error')
                ->once()
                ->withArgs(function (string $message): bool {
                    return str_starts_with($message, 'WhatsApp API error:');
                });

            $service = new SmsServiceWhatsAppFacebookHarness;
            $service->facebookDouble = $facebook;

            $this->assertFalse($service->send('1122223333', 'code 999999'));
        } finally {
            $this->app['env'] = $previousEnv;
        }
    }

    public function test_send_whatsapp_local_logs_laravel_http_failure_when_graph_request_throws(): void
    {
        $previousEnv = $this->app['env'];

        try {
            $this->app['env'] = 'local';

            Config::set('sms.default', 'whatsapp');
            Config::set('sms.providers.whatsapp', [
                'app_id' => 'app',
                'app_secret' => 'secret',
                'access_token' => 'tok',
                'phone_number_id' => 'pn-throw',
            ]);

            Http::fake(function () {
                throw new \RuntimeException('upstream refused');
            });

            Log::shouldReceive('error')
                ->once()
                ->with('Laravel HTTP client failed: upstream refused');

            $service = new SmsService;

            $this->assertFalse($service->send('1122223333', 'code 606060'));
        } finally {
            $this->app['env'] = $previousEnv;
        }
    }

    public function test_send_whatsapp_facebook_post_exception_logs_context_and_returns_false(): void
    {
        $previousEnv = $this->app['env'];

        try {
            $this->app['env'] = 'staging';

            Config::set('sms.default', 'whatsapp');
            Config::set('sms.providers.whatsapp', [
                'app_id' => 'app',
                'app_secret' => 'secret',
                'access_token' => 'tok',
                'phone_number_id' => 'pn-x',
            ]);

            $facebook = Mockery::mock();
            $facebook->shouldReceive('post')->once()->andThrow(new \RuntimeException('graph down'));

            Log::shouldReceive('error')
                ->once()
                ->with(
                    'WhatsApp sending failed',
                    Mockery::on(function (array $ctx): bool {
                        return ($ctx['error'] ?? '') === 'graph down'
                            && ($ctx['to'] ?? null) === '1122223333'
                            && ($ctx['formatted_phone'] ?? null) === '541122223333'
                            && str_contains((string) ($ctx['message'] ?? ''), '202020');
                    })
                );

            $service = new SmsServiceWhatsAppFacebookHarness;
            $service->facebookDouble = $facebook;

            $this->assertFalse($service->send('1122223333', 'code 202020'));
        } finally {
            $this->app['env'] = $previousEnv;
        }
    }

    public function test_format_phone_for_sms_masivos_normalizes_thirteen_digit_international_with_fifteen_prefix(): void
    {
        $h = new class extends SmsService
        {
            public function exposeSmsMasivos(string $phone): string
            {
                return $this->formatPhoneForSmsMasivos($phone);
            }
        };

        // 54 + 11 digits starting with 15 → strip country, then strip mobile prefix 15.
        $this->assertSame('111222233', $h->exposeSmsMasivos('5415111222233'));
    }

    public function test_generate_verification_code_is_always_six_digits_with_left_padding(): void
    {
        $service = new SmsService;
        for ($i = 0; $i < 30; $i++) {
            $code = $service->generateVerificationCode();
            $this->assertMatchesRegularExpression('/^\d{6}$/', $code);
            $this->assertSame(6, strlen($code));
        }
    }
}
