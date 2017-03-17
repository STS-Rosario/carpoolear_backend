<?php

namespace STS\Entities;

use Illuminate\Database\Eloquent\Model;

class TripDay extends Model
{
    protected $table = 'recurrent_trip_day';
    protected $fillable = ['day', 'hour', 'trip_id'];
    protected $hidden = [];

    public function trip()
    {
        return $this->belongsTo('STS\Entities\Trip', 'trip_id');
    }
}
