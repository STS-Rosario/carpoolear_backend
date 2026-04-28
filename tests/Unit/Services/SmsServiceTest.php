<?php

namespace Tests\Unit\Services;

use Illuminate\Support\Facades\Config;
use STS\Services\SmsService;
use Tests\TestCase;

class SmsServiceTest extends TestCase
{
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
}
