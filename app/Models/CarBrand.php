<?php

namespace STS\Models;

use Database\Factories\CarBrandFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CarBrand extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'argautos_id',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'argautos_id' => 'integer',
    ];

    protected static function newFactory()
    {
        return CarBrandFactory::new();
    }

    public function models(): HasMany
    {
        return $this->hasMany(CarModel::class, 'car_brand_id');
    }

    public function cars(): HasMany
    {
        return $this->hasMany(Car::class, 'car_brand_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
