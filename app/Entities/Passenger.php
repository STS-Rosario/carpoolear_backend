<?php

namespace STS\Entities;

use Illuminate\Database\Eloquent\Model;

class Passenger extends Model
{
    const STATE_PENDING = 0;
    const STATE_ACCEPTED = 1;
    const STATE_REJECTED = 2;
    const STATE_CANCELED = 3;

    const TYPE_CONDUCTOR = 0;
    const TYPE_PASAJERO = 1;
    const TYPE_CONDUCTORRECURRENTE = 2;

    protected $table = 'trip_passengers';
    protected $fillable = [
        'user_id',
        'trip_id',
        'passenger_type',
        'request_state',
    ];
    protected $hidden = [];

    public function user()
    {
        return $this->belongsTo('STS\User', 'user_id');
    }

    public function trip()
    {
        return $this->belongsTo('STS\Entities\Trip', 'trip_id');
    }
}
