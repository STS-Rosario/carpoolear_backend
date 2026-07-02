<?php

namespace STS\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ManualIdentityValidation extends Model
{
    protected $table = 'manual_identity_validations';

    const REVIEW_STATUS_PENDING = 'pending';

    const REVIEW_STATUS_AWAITING_PHOTOS = 'awaiting_photos';

    const REVIEW_STATUS_APPROVED = 'approved';

    const REVIEW_STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'user_id',
        'submitted_at',
        'submission_count',
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
        'private_admin_note',
        'manual_validation_started_at',
        'images_purged_at',
    ];

    protected function casts(): array
    {
        return [
            'paid' => 'boolean',
            'submission_count' => 'integer',
            'submitted_at' => 'datetime',
            'paid_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'manual_validation_started_at' => 'datetime',
            'images_purged_at' => 'datetime',
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
        return ! empty($this->front_image_path) || ! empty($this->back_image_path) || ! empty($this->selfie_image_path);
    }

    public function markAwaitingPhotos(): void
    {
        $this->review_status = self::REVIEW_STATUS_AWAITING_PHOTOS;
    }

    public function markPendingReview(): void
    {
        $this->review_status = self::REVIEW_STATUS_PENDING;
    }

    public function markPaidAndAwaitingPhotosIfNeeded(): void
    {
        $this->paid = true;
        if ($this->paid_at === null) {
            $this->paid_at = now();
        }
        if ($this->submitted_at === null) {
            $this->markAwaitingPhotos();
        }
    }

    /**
     * Paid requests with submitted documents awaiting admin review.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<self>  $query
     * @return \Illuminate\Database\Eloquent\Builder<self>
     */
    public function scopeReadyForAdminReview($query)
    {
        return $query
            ->where('paid', true)
            ->where('review_status', self::REVIEW_STATUS_PENDING)
            ->whereNotNull('submitted_at');
    }
}
