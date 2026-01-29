<?php

namespace STS\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MercadoPagoRejectedValidation extends Model
{
    protected $table = 'mercado_pago_rejected_validations';

    protected $fillable = [
        'user_id',
        'reject_reason',
        'mp_payload',
        'approved_at',
        'approved_by',
        'review_status',
        'review_note',
        'reviewed_at',
        'reviewed_by',
    ];

    protected function casts(): array
    {
        return [
            'mp_payload' => 'array',
            'approved_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
