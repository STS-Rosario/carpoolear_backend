<?php

namespace STS\Models;

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
}
