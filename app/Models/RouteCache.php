<?php

namespace STS\Models;

use Illuminate\Database\Eloquent\Model;

class RouteCache extends Model
{
    protected $table = 'route_cache';
    
    protected $fillable = [
        'points',
        'route_data',
        'expires_at',
        'hashed_points'
    ];

    protected $casts = [
        'points' => 'array',
        'route_data' => 'array',
        'expires_at' => 'datetime'
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            $model->hashed_points = hash('sha256', json_encode($model->points));
        });

        static::updating(function ($model) {
            if ($model->isDirty('points')) {
                $model->hashed_points = hash('sha256', json_encode($model->points));
            }
        });
    }
} 