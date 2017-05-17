<?php

namespace STS\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/*************************************************
 *  Clase Trip:
 *
 *             ->  es_pasajero se llama is_passenger
 *             ->  esta_carpooleado y el contador de pasajero no existen más
 *             ->  activo dejo de funcionar
 *
 *************************************************/

class Trip extends Model
{
    use SoftDeletes;

    const FINALIZADO = 0;
    const ACTIVO = 1;

    const PRIVACY_PUBLIC = 2;
    const PRIVACY_FRIENDS = 0;
    const PRIVACY_FOF = 1;

    protected $table = 'trips';

    protected $fillable = [
        'user_id',
        'from_town',
        'to_town',
        'trip_date',
        'description',
        'total_seats',
        'friendship_type_id',
        'distance',
        'estimated_time',
        'co2',
        'es_recurrente',
        'is_passenger',
        'mail_send',
        'return_trip_id',
        'enc_path',
        'car_id',
    ];

    protected $hidden = [
        'enc_path',
    ];

    protected $appends = [
        'passenger_count', 'seats_available', 'is_driver',
    ];

    protected $casts = [
        'is_passenger'  => 'boolean',
        'es_recurrente' => 'boolean',
    ];

    protected $dates = [
        'deleted_at', 'trip_date',
    ];

    public function user()
    {
        return $this->belongsTo('STS\User', 'user_id');
    }

    public function car()
    {
        return $this->belongsTo('STS\Entities\Car', 'car_id');
    }

    public function passenger()
    {
        return $this->hasMany('STS\Entities\Passenger', 'trip_id')->with('user');
    }

    public function passengerAccepted()
    {
        return $this->passenger()->whereRequestState(Passenger::STATE_ACCEPTED)->where('user_id', '<>', $this->user_id);
    }

    public function passengerPending()
    {
        return $this->passenger()->whereRequestState(Passenger::STATE_PENDING);
    }

    public function days()
    {
        return $this->hasMany('STS\Entities\TripDay', 'trip_id');
    }

    public function points()
    {
        return $this->hasMany('STS\Entities\TripPoint', 'trip_id');
    }

    public function califications()
    {
        return $this->hasMany('STS\Entities\Calification', 'viajes_id');
    }

    public function outbound()
    {
        return $this->hasOne('STS\Entities\Trip', 'return_trip_id');
    }

    public function inbound()
    {
        return $this->belongsTo('STS\Entities\Trip', 'return_trip_id');
    }

    public function conversation()
    {
        return $this->hasOne('STS\Entities\Conversation', 'trip_id');
    }

    public function getPassengerCountAttribute()
    {
        return $this->passengerAccepted()->count();
    }

    public function isPending($user) 
    {
        return $this->passengerPending()->where('user_id', $user->id)->count() > 0;
    }

    public function isPassenger($user) 
    {
        return $this->passengerAccepted->where('user_id', $user->id)->count() > 0;
    }

    public function getSeatsAvailableAttribute()
    {
        return $this->total_seats - $this->passengerAccepted()->count();
    }

    public function getIsDriverAttribute()
    {
        return ! $this->is_passenger;
    }

    public function setDescriptionAttribute($value)
    {
        $this->attributes['description'] = htmlentities($value);
    }

    public function checkFriendship($user)
    {
        $conductor = $this->user;
        $fiends = $conductor->friends()->whereId($user->id)->first();
        $fof = $conductor->relativeFriends()->whereId($user->id)->first();

        if ($conductor->id == $user->id) {
            return true;
        }

        switch ($this->friendship_type_id) {
            case self::PRIVACY_PUBLIC:
                return true;
            case self::PRIVACY_FRIENDS:
                return ! is_null($fiends);
            case self::PRIVACY_FOF:
                return ! is_null($fiends) || ! is_null($fof);
        }
    }
}
