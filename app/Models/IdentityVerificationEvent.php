<?php

namespace STS\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IdentityVerificationEvent extends Model
{
    public $timestamps = false;

    protected $table = 'identity_verification_events';

    protected $fillable = [
        'user_id',
        'method',
        'name',
        'reason',
        'attempt_id',
        'related_type',
        'related_id',
        'surface',
        'platform',
        'app_version',
        'metadata',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'created_at' => 'datetime',
            'related_id' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
