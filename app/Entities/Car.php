<?php

namespace STS\Entities;

use Illuminate\Database\Eloquent\Model;

class Car extends Model
{
    protected $table = 'cars';
    protected $fillable = ['patente', 'description', 'user_id'];
    protected $hidden = ['created_at', 'updated_at'];
    protected $appends = ['trips_count'];

    public function user()
    {
        return $this->belongsTo('STS\User', 'user_id');
    }

    public function trips()
    {
        return $this->hasMany('STS\Entities\Trip', 'car_id');
    }


    public function getTripsCountAttribute()
    {
        return $this->trips()->count(); 
    }
}
