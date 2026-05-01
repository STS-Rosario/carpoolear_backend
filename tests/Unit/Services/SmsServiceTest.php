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
}
