<?php

namespace Tests\Unit\Services\Logic;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use STS\Models\PhoneVerification;
use STS\Models\User;
use STS\Repository\PhoneVerificationRepository;
use STS\Services\Logic\PhoneVerificationManager;
use STS\Services\SmsService;
use Tests\TestCase;

class PhoneVerificationManagerTest extends TestCase
{
    private function manager(): PhoneVerificationManager
    {
        return new PhoneVerificationManager(
            new PhoneVerificationRepository,
            new SmsService
        );
    }

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('sms.default', 'local');
        Config::set('sms.verification.resend_cooldown_minutes', 0);
        Config::set('sms.verification.expires_in_minutes', 30);
        Config::set('sms.verification.max_failed_attempts', 5);
    }

    public function test_validator_send_requires_phone(): void
    {
        $v = $this->manager()->validatorSend([]);
        $this->assertTrue($v->fails());
    }

    public function test_validator_verify_requires_exact_six_character_code(): void
    {
        $manager = $this->manager();

        $missing = $manager->validatorVerify([]);
        $this->assertTrue($missing->fails());
        $this->assertTrue($missing->errors()->has('code'));

        $short = $manager->validatorVerify(['code' => '12345']);
        $this->assertTrue($short->fails());
        $this->assertTrue($short->errors()->has('code'));

        $ok = $manager->validatorVerify(['code' => '123456']);
        $this->assertFalse($ok->fails());
    }

    public function test_send_verification_code_creates_row_and_returns_payload(): void
    {
        $user = User::factory()->create();
        $request = Request::create('/api/phone/send', 'POST', ['phone' => '1123456789']);

        $result = $this->manager()->sendVerificationCode($user, $request);

        $this->assertNotNull($result);
        $this->assertArrayHasKey('verification', $result);
        $this->assertDatabaseHas('phone_verifications', [
            'user_id' => $user->id,
            'verified' => false,
        ]);
        $this->assertSame(6, strlen((string) $result['verification']->verification_code));
    }

    public function test_send_verification_code_returns_validation_errors_when_phone_is_missing(): void
    {
        $user = User::factory()->create();
        $request = Request::create('/api/phone/send', 'POST', []);
        $manager = $this->manager();

        $result = $manager->sendVerificationCode($user, $request);

        $this->assertNull($result);
        $this->assertTrue($manager->getErrors()->has('phone'));
    }

    public function test_send_verification_code_rejected_when_phone_verified_for_other_user(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        PhoneVerification::create([
            'user_id' => $owner->id,
            'phone_number' => '+541112223344',
            'verified' => true,
            'verified_at' => Carbon::now(),
        ]);

        $request = Request::create('/api/phone/send', 'POST', ['phone' => '1112223344']);
        $manager = $this->manager();
        $result = $manager->sendVerificationCode($other, $request);

        $this->assertNull($result);
        $this->assertNotNull($manager->getErrors());
    }

    public function test_send_verification_code_returns_blocked_error_for_blocked_pending_verification(): void
    {
        Config::set('sms.verification.max_failed_attempts', 1);
        $user = User::factory()->create();

        PhoneVerification::create([
            'user_id' => $user->id,
            'phone_number' => '+541199991111',
            'verification_code' => '123456',
            'code_sent_at' => Carbon::now(),
            'verified' => false,
            'failed_attempts' => 1,
            'resend_count' => 0,
        ]);

        $manager = $this->manager();
        $result = $manager->sendVerificationCode($user, Request::create('/', 'POST', ['phone' => '1199991111']));

        $this->assertNull($result);
        $this->assertSame('Too many failed attempts. Please request a new code.', $manager->getErrors()['verification']);
    }

    public function test_send_verification_code_enforces_resend_cooldown_message(): void
    {
        Config::set('sms.verification.resend_cooldown_minutes', 5);
        $user = User::factory()->create();

        PhoneVerification::create([
            'user_id' => $user->id,
            'phone_number' => '+541198887777',
            'verification_code' => '111111',
            'code_sent_at' => Carbon::now(),
            'verified' => false,
            'failed_attempts' => 0,
            'resend_count' => 0,
        ]);

        $manager = $this->manager();
        $result = $manager->sendVerificationCode($user, Request::create('/', 'POST', ['phone' => '1198887777']));

        $this->assertNull($result);
        $this->assertStringContainsString('Please wait', $manager->getErrors()['verification']);
    }

    public function test_verify_phone_number_updates_user_on_success(): void
    {
        $user = User::factory()->create();
        $sendReq = Request::create('/api/phone/send', 'POST', ['phone' => '1133334444']);
        $sent = $this->manager()->sendVerificationCode($user, $sendReq);
        $this->assertNotNull($sent);

        $code = PhoneVerification::where('user_id', $user->id)->value('verification_code');
        $verifyReq = Request::create('/api/phone/verify', 'POST', ['code' => $code]);

        $result = $this->manager()->verifyPhoneNumber($user->fresh(), $verifyReq);

        $this->assertNotNull($result);
        $this->assertTrue($result['phone_verified']);
        $user->refresh();
        $this->assertTrue($user->phone_verified);
        $this->assertNotNull($user->phone_verified_at);
        $this->assertNotNull($user->mobile_phone);
    }

    public function test_verify_phone_number_fails_on_wrong_code_and_increments_attempts(): void
    {
        Config::set('sms.verification.max_failed_attempts', 2);
        $user = User::factory()->create();
        $manager = $this->manager();
        $manager->sendVerificationCode($user, Request::create('/', 'POST', ['phone' => '1144445555']));

        $row = PhoneVerification::where('user_id', $user->id)->first();
        $this->assertNotNull($row);

        $this->assertNull($manager->verifyPhoneNumber($user, Request::create('/', 'POST', ['code' => '000000'])));
        $this->assertSame(1, (int) $row->fresh()->failed_attempts);

        $this->assertNull($manager->verifyPhoneNumber($user, Request::create('/', 'POST', ['code' => '000000'])));
        $this->assertTrue($row->fresh()->isBlocked());

        $this->assertNull($manager->verifyPhoneNumber($user, Request::create('/', 'POST', ['code' => $row->fresh()->verification_code])));
    }

    public function test_verify_phone_number_fails_when_code_expired(): void
    {
        $user = User::factory()->create();
        $this->manager()->sendVerificationCode($user, Request::create('/', 'POST', ['phone' => '1155556666']));

        $row = PhoneVerification::where('user_id', $user->id)->first();
        $row->forceFill(['code_sent_at' => Carbon::now()->subDays(2)])->saveQuietly();

        $manager = $this->manager();
        $this->assertNull($manager->verifyPhoneNumber($user, Request::create('/', 'POST', ['code' => $row->verification_code])));
        $errors = $manager->getErrors();
        $this->assertIsArray($errors);
        $this->assertArrayHasKey('code', $errors);
    }

    public function test_resend_verification_code_updates_code(): void
    {
        $user = User::factory()->create();
        $manager = $this->manager();
        $manager->sendVerificationCode($user, Request::create('/', 'POST', ['phone' => '1166667777']));
        $first = PhoneVerification::where('user_id', $user->id)->value('verification_code');

        $result = $manager->resendVerificationCode($user);
        $this->assertNotNull($result);

        $second = PhoneVerification::where('user_id', $user->id)->value('verification_code');
        $this->assertNotSame($first, $second);
    }

    public function test_resend_verification_code_fails_when_no_pending_verification_exists(): void
    {
        $user = User::factory()->create();
        $manager = $this->manager();

        $result = $manager->resendVerificationCode($user);

        $this->assertNull($result);
        $this->assertSame('No pending verification found', $manager->getErrors()['verification']);
    }

    public function test_resend_verification_code_fails_when_pending_verification_is_blocked(): void
    {
        Config::set('sms.verification.max_failed_attempts', 1);
        $user = User::factory()->create();
        PhoneVerification::create([
            'user_id' => $user->id,
            'phone_number' => '+541177766655',
            'verification_code' => '123123',
            'code_sent_at' => Carbon::now()->subMinutes(10),
            'verified' => false,
            'failed_attempts' => 1,
            'resend_count' => 0,
        ]);

        $manager = $this->manager();
        $result = $manager->resendVerificationCode($user);

        $this->assertNull($result);
        $this->assertSame('Too many failed attempts. Please request a new code.', $manager->getErrors()['verification']);
    }

    public function test_get_verification_status_returns_no_phone_when_user_has_no_mobile_phone(): void
    {
        $user = User::factory()->create([
            'mobile_phone' => null,
            'phone_verified' => false,
            'phone_verified_at' => null,
        ]);

        $status = $this->manager()->getVerificationStatus($user);

        $this->assertFalse($status['has_phone']);
        $this->assertFalse($status['phone_verified']);
        $this->assertArrayNotHasKey('pending_verification', $status);
    }

    public function test_get_verification_status_includes_pending_verification_details(): void
    {
        Carbon::setTestNow('2026-12-01 10:00:00');
        $user = User::factory()->create([
            'mobile_phone' => '+541199887766',
            'phone_verified' => false,
            'phone_verified_at' => null,
        ]);

        PhoneVerification::create([
            'user_id' => $user->id,
            'phone_number' => '+541199887766',
            'verification_code' => '123456',
            'code_sent_at' => Carbon::now(),
            'verified' => false,
            'failed_attempts' => 1,
            'resend_count' => 0,
        ]);

        $status = $this->manager()->getVerificationStatus($user->fresh());

        $this->assertTrue($status['has_phone']);
        $this->assertFalse($status['phone_verified']);
        $this->assertArrayHasKey('pending_verification', $status);
        $this->assertSame('+541199887766', $status['pending_verification']['phone']);
        $this->assertSame(1, $status['pending_verification']['failed_attempts']);
        $this->assertFalse($status['pending_verification']['is_blocked']);

        Carbon::setTestNow();
    }

    public function test_get_verification_stats_delegates_to_repository(): void
    {
        $user = User::factory()->create();
        PhoneVerification::create([
            'user_id' => $user->id,
            'phone_number' => '+549990001122',
            'verified' => true,
            'verified_at' => Carbon::now(),
        ]);

        $stats = $this->manager()->getVerificationStats($user);
        $this->assertSame(1, (int) $stats->total_attempts);
    }
}
