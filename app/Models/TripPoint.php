<?php

namespace STS\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TripPoint extends Model
{
    use HasFactory;

    protected static function newFactory()
    {
        return \Database\Factories\TripPointFactory::new();
    }

    protected $table = 'trips_points';

    /**
     * @return list<string>
     */
    public function getFillable(): array
    {
        return [
            'address',
            'json_address',
            'lat',
            'lng',
            'sin_lat',
            'sin_lng',
            'cos_lat',
            'cos_lng',
            'trip_id',
        ];
    }

    /**
     * @return list<string>
     */
    public function getHidden(): array
    {
        return [
            'created_at',
            'updated_at',
            'sin_lat',
            'sin_lng',
            'cos_lat',
            'cos_lng',
        ];
    }

    protected function casts(): array
    {
        return [
            'json_address' => 'array',
            'is_passenger' => 'boolean',
        ];
    }

    public function setLatAttribute($value)
    {
        $this->attributes['lat'] = $value;
        $this->attributes['sin_lat'] = sin(deg2rad($value));
        $this->attributes['cos_lat'] = cos(deg2rad($value));
    }

    public function setLngAttribute($value)
    {
        $this->attributes['lng'] = $value;
        $this->attributes['sin_lng'] = sin(deg2rad($value));
        $this->attributes['cos_lng'] = cos(deg2rad($value));
    }

    public function trip()
    {
        return $this->belongsTo('STS\Models\Trip', 'trip_id');
    }
}
