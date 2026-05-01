<?php

namespace STS\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PhoneVerification extends Model
{
    protected $table = 'phone_verifications';

    protected $fillable = [
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

    protected function casts(): array
    {
        return [
            'verified' => 'boolean',
            'code_sent_at' => 'datetime',
            'verified_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isBlocked(): bool
    {
        $max = (int) config('sms.verification.max_failed_attempts', 5);

        return (int) $this->failed_attempts >= $max;
    }

    public function canResend(): bool
    {
        $cooldown = (int) config('sms.verification.resend_cooldown_minutes', 2);
        if ($cooldown === 0) {
            return true;
        }
        if ($this->code_sent_at === null) {
            return true;
        }

        return now()->greaterThanOrEqualTo($this->getNextResendTime());
    }

    public function getNextResendTime(): Carbon
    {
        $cooldown = (int) config('sms.verification.resend_cooldown_minutes', 2);
        $base = $this->code_sent_at ?? now();

        return $base->copy()->addMinutes($cooldown);
    }

    public function incrementResendCount(): void
    {
        $this->resend_count = (int) $this->resend_count + 1;
        $this->save();
    }

    public function isExpired(): bool
    {
        $minutes = (int) config('sms.verification.expires_in_minutes', 5);
        if ($this->code_sent_at === null) {
            return true;
        }
        $deadline = $this->code_sent_at->copy()->addMinutes($minutes);

        return now()->greaterThanOrEqualTo($deadline);
    }

    public function verifyCode(string $code): bool
    {
        return hash_equals((string) $this->verification_code, (string) $code);
    }

    public function incrementFailedAttempts(): bool
    {
        $this->failed_attempts = (int) $this->failed_attempts + 1;
        $this->save();

        return $this->isBlocked();
    }

    public function markAsVerified(): void
    {
        $this->verified = true;
        $this->verified_at = now();
        $this->save();
    }

    public function resetFailedAttempts(): void
    {
        $this->failed_attempts = 0;
        $this->save();
    }
}
