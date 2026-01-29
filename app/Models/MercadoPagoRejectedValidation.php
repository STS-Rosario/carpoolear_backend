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
    ];

    protected function casts(): array
    {
        return [
            'mp_payload' => 'array',
            'approved_at' => 'datetime',
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
}
