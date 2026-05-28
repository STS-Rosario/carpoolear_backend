<?php

namespace STS\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
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

    protected $fillable = ['patente', 'description', 'user_id'];

    protected $hidden = ['created_at', 'updated_at'];

    protected $appends = ['trips_count'];

    public function user()
    {
        return $this->belongsTo('STS\Models\User', 'user_id');
    }

    public function trips()
    {
        return $this->hasMany('STS\Models\Trip', 'car_id');
    }

    public function getTripsCountAttribute()
    {
        return $this->trips()->count();
    }
}
