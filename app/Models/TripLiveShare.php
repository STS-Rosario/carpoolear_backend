<?php

namespace STS\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TripLiveShare extends Model
{
    use HasFactory;

    protected static function newFactory()
    {
        return \Database\Factories\TripLiveShareFactory::new();
    }

    protected $table = 'trip_live_shares';

    protected $fillable = [
        'trip_id',
        'user_id',
        'share_token',
        'is_active',
        'lat',
        'lng',
        'recorded_at',
        'stop_reminder_sent_at',
        'auto_stopped_at',
        'started_at',
        'stopped_at',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'lat' => 'float',
            'lng' => 'float',
            'recorded_at' => 'datetime',
            'stop_reminder_sent_at' => 'datetime',
            'auto_stopped_at' => 'datetime',
            'started_at' => 'datetime',
            'stopped_at' => 'datetime',
        ];
    }

    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
