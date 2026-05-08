<?php

namespace STS\Models;

use Illuminate\Database\Eloquent\Model;
use STS\Casts\UtcDatetime;

class MaintenanceSchedule extends Model
{
    protected $table = 'maintenance_schedules';

    protected $fillable = [
        'starts_at',
        'ends_at',
        'message',
        'mode',
        'cancelled_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => UtcDatetime::class,
            'ends_at' => UtcDatetime::class,
            'cancelled_at' => UtcDatetime::class,
            'completed_at' => UtcDatetime::class,
        ];
    }

    /**
     * Still eligible for overlap checks and cron activation.
     */
    public function scopePending($query)
    {
        return $query->whereNull('cancelled_at')->whereNull('completed_at');
    }

    public function isPending(): bool
    {
        return $this->cancelled_at === null && $this->completed_at === null;
    }
}
