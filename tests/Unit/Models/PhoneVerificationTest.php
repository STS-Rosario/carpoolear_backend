<?php

namespace Tests\Unit\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Config;
use STS\Models\PhoneVerification;
use STS\Models\User;
use Tests\TestCase;

class PhoneVerificationTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function makeVerification(User $user, array $overrides = []): PhoneVerification
    {
        return PhoneVerification::query()->create(array_merge([
            'user_id' => $user->id,
            'phone_number' => '+54911'.random_int(10000000, 99999999),
            'verified' => false,
            'verification_code' => '482910',
            'code_sent_at' => now(),
            'ip_address' => '127.0.0.1',
            'failed_attempts' => 0,
            'resend_count' => 0,
            'verified_at' => null,
        ], $overrides));
    }

    public function test_fillable_lists_persisted_columns(): void
    {
        $expected = [
            'user_id',
            'phone_number',
            'verified',
            'verification_code',
            'code_sent_at',
            'ip_address',
            'failed_attempts',
            'resend_count',
            'verified_at',
        ];

        $this->assertSame($expected, (new PhoneVerification)->getFillable());
    }

    public function test_casts_include_verified_and_timestamps(): void
    {
        $casts = (new PhoneVerification)->getCasts();

        $this->assertSame('boolean', $casts['verified']);
        $this->assertSame('datetime', $casts['code_sent_at']);
        $this->assertSame('datetime', $casts['verified_at']);
    }

    public function test_user_relation_is_belongs_to_user(): void
    {
        $this->assertInstanceOf(BelongsTo::class, (new PhoneVerification)->user());
    }

    public function test_is_blocked_uses_configured_max_inclusive(): void
    {
        Config::set('sms.verification.max_failed_attempts', 4);

        $user = User::factory()->create();
        $atLimit = $this->makeVerification($user, ['failed_attempts' => 4]);
        $below = $this->makeVerification($user, ['failed_attempts' => 3]);

        $this->assertTrue($atLimit->fresh()->isBlocked());
        $this->assertFalse($below->fresh()->isBlocked());
    }

    public function test_is_blocked_casts_string_attempts_from_storage(): void
    {
        Config::set('sms.verification.max_failed_attempts', 3);

        $user = User::factory()->create();
        $pv = $this->makeVerification($user, ['failed_attempts' => 0]);
        $pv->forceFill(['failed_attempts' => '3'])->save();

        $this->assertTrue($pv->fresh()->isBlocked());
    }

    public function test_can_resend_when_cooldown_is_zero_without_touching_sent_at(): void
    {
        Config::set('sms.verification.resend_cooldown_minutes', 0);
        Carbon::setTestNow('2026-07-01 12:00:00');

        $user = User::factory()->create();
        $pv = $this->makeVerification($user, [
            'code_sent_at' => Carbon::parse('2026-07-01 12:00:00'),
        ]);

        $this->assertTrue($pv->fresh()->canResend());
    }

    public function test_can_resend_when_code_never_sent(): void
    {
        Config::set('sms.verification.resend_cooldown_minutes', 10);

        $user = User::factory()->create();
        $pv = $this->makeVerification($user, ['code_sent_at' => null]);

        $this->assertTrue($pv->fresh()->canResend());
    }

    public function test_can_resend_follows_next_resend_time_after_cooldown(): void
    {
        Config::set('sms.verification.resend_cooldown_minutes', 5);
        Carbon::setTestNow('2026-07-01 12:00:00');

        $user = User::factory()->create();
        $sent = Carbon::parse('2026-07-01 12:00:00');
        $pv = $this->makeVerification($user, ['code_sent_at' => $sent]);

        Carbon::setTestNow('2026-07-01 12:04:59');
        $this->assertFalse($pv->fresh()->canResend());

        Carbon::setTestNow('2026-07-01 12:05:00');
        $this->assertTrue($pv->fresh()->canResend());
    }

    public function test_get_next_resend_time_uses_sent_at_not_now_when_present(): void
    {
        Config::set('sms.verification.resend_cooldown_minutes', 3);
        Carbon::setTestNow('2026-07-01 15:00:00');

        $user = User::factory()->create();
        $sent = Carbon::parse('2026-07-01 10:00:00');
        $pv = $this->makeVerification($user, ['code_sent_at' => $sent]);

        $expected = $sent->copy()->addMinutes(3);
        $this->assertTrue($pv->fresh()->getNextResendTime()->equalTo($expected));
    }

    public function test_increment_resend_count_persists_increment(): void
    {
        $user = User::factory()->create();
        $pv = $this->makeVerification($user, ['resend_count' => 2]);

        $pv->fresh()->incrementResendCount();

        $this->assertSame(3, $pv->fresh()->resend_count);
    }

    public function test_is_expired_when_no_code_sent_timestamp(): void
    {
        Config::set('sms.verification.expires_in_minutes', 99);

        $user = User::factory()->create();
        $pv = $this->makeVerification($user, ['code_sent_at' => null]);

        $this->assertTrue($pv->fresh()->isExpired());
    }

    public function test_is_expired_respects_deadline_from_configured_minutes(): void
    {
        Config::set('sms.verification.expires_in_minutes', 10);
        Carbon::setTestNow('2026-08-10 09:00:00');

        $user = User::factory()->create();
        $sent = Carbon::parse('2026-08-10 08:55:00');
        $pv = $this->makeVerification($user, ['code_sent_at' => $sent]);

        Carbon::setTestNow('2026-08-10 09:04:59');
        $this->assertFalse($pv->fresh()->isExpired());

        Carbon::setTestNow('2026-08-10 09:05:00');
        $this->assertTrue($pv->fresh()->isExpired());
    }

    public function test_verify_code_compares_string_normalization(): void
    {
        $user = User::factory()->create();
        $pv = $this->makeVerification($user, ['verification_code' => '0012']);

        $this->assertTrue($pv->fresh()->verifyCode('0012'));
        $this->assertFalse($pv->fresh()->verifyCode('12'));
        $this->assertFalse($pv->fresh()->verifyCode('wrong'));

        $numeric = $this->makeVerification($user, ['verification_code' => '42']);
        $this->assertTrue($numeric->fresh()->verifyCode(42));
    }

    public function test_increment_failed_attempts_returns_blocked_flag_and_persists(): void
    {
        Config::set('sms.verification.max_failed_attempts', 2);

        $user = User::factory()->create();
        $pv = $this->makeVerification($user, ['failed_attempts' => 0]);

        $this->assertFalse($pv->fresh()->incrementFailedAttempts());
        $this->assertSame(1, $pv->fresh()->failed_attempts);

        $this->assertTrue($pv->fresh()->incrementFailedAttempts());
        $this->assertSame(2, $pv->fresh()->failed_attempts);
    }

    public function test_mark_as_verified_sets_boolean_timestamp_and_saves(): void
    {
        Carbon::setTestNow('2026-01-20 18:30:00');

        $user = User::factory()->create();
        $pv = $this->makeVerification($user, ['verified' => false, 'verified_at' => null]);

        $pv->fresh()->markAsVerified();
        $row = $pv->fresh();

        $this->assertTrue($row->verified);
        $this->assertNotNull($row->verified_at);
        $this->assertTrue($row->verified_at->equalTo(Carbon::parse('2026-01-20 18:30:00')));
    }

    public function test_reset_failed_attempts_writes_zero(): void
    {
        $user = User::factory()->create();
        $pv = $this->makeVerification($user, ['failed_attempts' => 7]);

        $pv->fresh()->resetFailedAttempts();

        $this->assertSame(0, $pv->fresh()->failed_attempts);
    }
}
