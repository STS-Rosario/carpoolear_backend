<?php

namespace STS\Models;

use Illuminate\Database\Eloquent\Model;

class Passenger extends Model
{
    const STATE_PENDING = 0;

    const STATE_ACCEPTED = 1;

    const STATE_REJECTED = 2;

    const STATE_CANCELED = 3;

    const STATE_WAITING_PAYMENT = 4;

    // CANCELED STATES

    const CANCELED_REQUEST = 0;

    const CANCELED_DRIVER = 1;

    const CANCELED_PASSENGER = 2;

    const CANCELED_PASSENGER_WHILE_PAYING = 3;

    const CANCELED_SYSTEM = 4;

    // PASSENGER TYPE

    const TYPE_CONDUCTOR = 0;

    const TYPE_PASAJERO = 1;

    const TYPE_CONDUCTORRECURRENTE = 2;

    protected $table = 'trip_passengers';

    protected $fillable = [
        'user_id',
        'trip_id',
        'passenger_type',
        'request_state',
        'canceled_state',
    ];

    protected $hidden = [];

    protected $casts = [
        'payment_info' => 'array',
    ];

    protected function casts(): array
    {
        return [
            'payment_info' => 'array'
        ];
    }

    public function user()
    {
        return $this->belongsTo('STS\Models\User', 'user_id');
    }

    public function trip()
    {
        return $this->belongsTo('STS\Models\Trip', 'trip_id');
    }

    public function ratingGiven()
    {
        return $this->hasMany('STS\Models\Rating', 'user_id_from')->where('trip_id', $this->trip_id);
    }

    public function ratingReceived()
    {
        return $this->hasMany('STS\Models\Rating', 'user_id_to')->where('trip_id', $this->trip_id);
    }
}
