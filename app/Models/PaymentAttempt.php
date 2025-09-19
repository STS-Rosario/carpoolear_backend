<?php

namespace STS\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentAttempt extends Model
{
    protected $table = 'payment_attempts';

    const STATUS_PENDING = 'pending';
    const STATUS_COMPLETED = 'completed';
    const STATUS_FAILED = 'failed';

    protected $fillable = [
        'payment_id',
        'payment_status',
        'trip_id',
        'user_id',
        'amount_cents',
        'error_message',
        'payment_data',
        'paid_at'
    ];

    protected $casts = [
        'payment_data' => 'array',
        'paid_at' => 'datetime'
    ];

    public function trip()
    {
        return $this->belongsTo(Trip::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Helper methods for payment status
    public function isPending()
    {
        return $this->payment_status === self::STATUS_PENDING;
    }

    public function isCompleted()
    {
        return $this->payment_status === self::STATUS_COMPLETED;
    }

    public function isFailed()
    {
        return $this->payment_status === self::STATUS_FAILED;
    }
} 