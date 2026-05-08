<?php

namespace STS\Models;

use Illuminate\Database\Eloquent\Model;

class MaintenanceAuditLog extends Model
{
    public $timestamps = false;

    protected $table = 'maintenance_audit_logs';

    protected $fillable = [
        'user_id',
        'action',
        'meta',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'meta' => 'array',
            'created_at' => 'datetime',
        ];
    }
}
