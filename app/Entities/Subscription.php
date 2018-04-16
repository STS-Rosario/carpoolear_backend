<?php

namespace STS\Entities;

use Illuminate\Database\Eloquent\Model;

class Car extends Model
{
    protected $table = 'subscription';
    protected $fillable = [
        'user_id', 'trip_date', 
        'from_address', 'from_json_address', 'from_lat', 'from_lng',
        'to_address', 'to_json_address', 'to_lat', 'to_lng',
        'state'
    ];
    protected $hidden = ['created_at', 'updated_at'];

    protected $appends = ['trips_count'];

    protected $dates = ['created_at', 'updated_at', 'trip_date'];

    
    public function user()
    {
        return $this->belongsTo('STS\User', 'user_id');
    } 
}
