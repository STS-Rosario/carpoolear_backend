<?php

namespace Tests\Unit\Services;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Mockery;
use STS\Services\SmsService;
use Tests\TestCase;

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

            Log::shouldReceive('info')
                ->once()
                ->with('WhatsApp API URL being called', Mockery::type('array'));

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

            Log::shouldReceive('info')
                ->once()
                ->with('WhatsApp API URL being called', Mockery::type('array'));

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

            Log::shouldReceive('info')
                ->ordered()
                ->once()
                ->with('WhatsApp API URL being called', Mockery::type('array'));
            Log::shouldReceive('info')
                ->once()
                ->with('WhatsApp message sent successfully via Laravel HTTP client to: 541122223333 with message: '.$message);

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

        Log::shouldReceive('info')
            ->once()
            ->withArgs(function (string $message): bool {
                return str_starts_with($message, 'SMS sent via SMS Masivos to: ')
                    && str_contains($message, ' with message: ')
                    && str_contains($message, '1122223333')
                    && str_contains($message, 'ping');
            });

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

        Log::shouldReceive('info')
            ->once()
            ->withArgs(function (string $message): bool {
                return str_starts_with($message, 'SMS test mode via SMS Masivos to: ')
                    && str_contains($message, ' with message: ');
            });

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
}
