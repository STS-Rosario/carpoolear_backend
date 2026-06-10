<?php

namespace STS\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Car extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected static function newFactory()
    {
        return \Database\Factories\CarFactory::new();
    }

    protected $table = 'cars';

    protected $fillable = [
        'patente',
        'description',
        'user_id',
        'car_brand_id',
        'car_model_id',
        'brand_other',
        'model_other',
        'car_color_id',
        'year',
    ];

    protected $hidden = ['created_at', 'updated_at'];

    protected $appends = ['trips_count', 'brand_name', 'model_name', 'color_name'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function trips()
    {
        return $this->hasMany(Trip::class, 'car_id');
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(CarBrand::class, 'car_brand_id');
    }

    public function carModel(): BelongsTo
    {
        return $this->belongsTo(CarModel::class, 'car_model_id');
    }

    public function color(): BelongsTo
    {
        return $this->belongsTo(CarColor::class, 'car_color_id');
    }

    public function getTripsCountAttribute()
    {
        return $this->trips()->count();
    }

    public function getBrandNameAttribute(): ?string
    {
        if ($this->relationLoaded('brand') && $this->brand) {
            return $this->brand->name;
        }

        return $this->brand_other;
    }

    public function getModelNameAttribute(): ?string
    {
        if ($this->relationLoaded('carModel') && $this->carModel) {
            return $this->carModel->name;
        }

        return $this->model_other;
    }

    public function getColorNameAttribute(): ?string
    {
        if ($this->relationLoaded('color') && $this->color) {
            return $this->color->name;
        }

        return null;
    }

    public function isComplete(): bool
    {
        if (! $this->hasValue($this->patente)) {
            return false;
        }

        if (! $this->hasValidYear()) {
            return false;
        }

        if (! $this->car_color_id) {
            return false;
        }

        if ($this->car_brand_id && $this->car_model_id) {
            return true;
        }

        return $this->hasValue($this->brand_other) && $this->hasValue($this->model_other);
    }

    private function hasValidYear(): bool
    {
        if ($this->year === null) {
            return false;
        }

        $year = (int) $this->year;

        return $year >= 1900 && $year <= (int) date('Y');
    }

    private function hasValue($value): bool
    {
        return $value !== null && trim((string) $value) !== '';
    }
}
