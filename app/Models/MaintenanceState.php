<?php

namespace STS\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use STS\Casts\UtcDatetime;

class MaintenanceState extends Model
{
    protected $table = 'maintenance_state';

    public $incrementing = false;

    protected $keyType = 'int';

    protected $fillable = [
        'is_active',
        'mode',
        'message',
        'ends_at',
        'source',
        'active_schedule_id',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'ends_at' => UtcDatetime::class,
        ];
    }

    public function activeSchedule(): BelongsTo
    {
        return $this->belongsTo(MaintenanceSchedule::class, 'active_schedule_id');
    }
}
