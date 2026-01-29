<?php

namespace STS\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ManualIdentityValidation extends Model
{
    protected $table = 'manual_identity_validations';

    const REVIEW_STATUS_PENDING = 'pending';
    const REVIEW_STATUS_APPROVED = 'approved';
    const REVIEW_STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'user_id',
        'submitted_at',
        'front_image_path',
        'back_image_path',
        'selfie_image_path',
        'payment_id',
        'paid',
        'paid_at',
        'review_status',
        'reviewed_by',
        'reviewed_at',
        'review_note',
    ];

    protected function casts(): array
    {
        return [
            'paid' => 'boolean',
            'submitted_at' => 'datetime',
            'paid_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function hasImages(): bool
    {
        return !empty($this->front_image_path) || !empty($this->back_image_path) || !empty($this->selfie_image_path);
    }
}
