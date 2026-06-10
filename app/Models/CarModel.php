<?php

namespace STS\Models;

use Database\Factories\CarModelFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CarModel extends Model
{
    use HasFactory;

    protected $fillable = [
        'car_brand_id',
        'name',
        'slug',
        'argautos_id',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'argautos_id' => 'integer',
        'car_brand_id' => 'integer',
    ];

    protected static function newFactory()
    {
        return CarModelFactory::new();
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(CarBrand::class, 'car_brand_id');
    }

    public function cars(): HasMany
    {
        return $this->hasMany(Car::class, 'car_model_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
