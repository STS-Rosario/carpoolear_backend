<?php

namespace STS\Models;

use Database\Factories\CarColorFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CarColor extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'hex',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    protected static function newFactory()
    {
        return CarColorFactory::new();
    }

    public function cars(): HasMany
    {
        return $this->hasMany(Car::class, 'car_color_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name');
    }
}
