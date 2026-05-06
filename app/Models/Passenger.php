<?php

namespace STS\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Passenger extends Model
{
    use HasFactory;

    protected static function newFactory()
    {
        return \Database\Factories\PassengerFactory::new();
    }

    const STATE_PENDING = 0; // @pest-mutate-ignore:DecrementInteger,IncrementInteger

    const STATE_ACCEPTED = 1; // @pest-mutate-ignore:DecrementInteger,IncrementInteger

    const STATE_REJECTED = 2; // @pest-mutate-ignore:DecrementInteger,IncrementInteger

    const STATE_CANCELED = 3; // @pest-mutate-ignore:DecrementInteger,IncrementInteger

    const STATE_WAITING_PAYMENT = 4; // @pest-mutate-ignore:DecrementInteger,IncrementInteger

    // CANCELED STATES

    const CANCELED_REQUEST = 0; // @pest-mutate-ignore:DecrementInteger,IncrementInteger

    const CANCELED_DRIVER = 1; // @pest-mutate-ignore:DecrementInteger,IncrementInteger

    const CANCELED_PASSENGER = 2; // @pest-mutate-ignore:DecrementInteger,IncrementInteger

    const CANCELED_PASSENGER_WHILE_PAYING = 3; // @pest-mutate-ignore:DecrementInteger,IncrementInteger

    const CANCELED_SYSTEM = 4; // @pest-mutate-ignore:DecrementInteger,IncrementInteger

    // PASSENGER TYPE

    const TYPE_CONDUCTOR = 0; // @pest-mutate-ignore:DecrementInteger,IncrementInteger

    const TYPE_PASAJERO = 1; // @pest-mutate-ignore:DecrementInteger,IncrementInteger

    const TYPE_CONDUCTORRECURRENTE = 2; // @pest-mutate-ignore:DecrementInteger,IncrementInteger

    protected $table = 'trip_passengers';

    /**
     * @return list<string>
     */
    public function getFillable(): array
    {
        return [
            'user_id',
            'trip_id',
            'passenger_type',
            'request_state',
            'canceled_state',
        ];
    }

    /**
     * @return list<string>
     */
    public function getHidden(): array
    {
        return [];
    }

    protected function casts(): array
    {
        return [
            'trip_id' => 'integer',
            'payment_info' => 'array',
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

    /**
     * Ratings this passenger's user gave on this trip (FK columns reference users.id).
     */
    public function ratingGiven()
    {
        return $this->hasMany('STS\Models\Rating', 'user_id_from', 'user_id')
            ->where('trip_id', $this->trip_id);
    }

    /**
     * Ratings this passenger's user received on this trip.
     */
    public function ratingReceived()
    {
        return $this->hasMany('STS\Models\Rating', 'user_id_to', 'user_id')
            ->where('trip_id', $this->trip_id);
    }
}
