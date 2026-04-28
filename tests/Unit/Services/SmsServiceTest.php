<?php

namespace Tests\Unit\Services;

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
}
