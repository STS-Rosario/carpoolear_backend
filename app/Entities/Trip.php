<?php namespace STS\Entities;

use Illuminate\Database\Eloquent\Model;
use STS\Entities\Passenger;

class Trip extends Model {
	const FINALIZADO 	= 0;
    const ACTIVO 		= 1;

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
		'is_active',
		'distance',
		'estimated_time',
		'co2',
		'es_recurrente',
		'esta_carpooleado',
		'tripscol',
		'es_pasajero',
		'mail_send'
	];
	protected $hidden = [];

	public function user() {
        return $this->belongsTo('STS\User','user_id');
    }


	public function passenger() {
        return $this->hasMany('STS\Entities\Passenger','trip_id')->with("user");
    } 

	public function pasajeros() {
		return $this->passenger()->whereRequestState(Passenger::STATE_ACEPTADO);
	}

	public function pendientes() {
		return $this->passenger()->whereRequestState(Passenger::STATE_PENDIENTE);
	}

	public function days() {
		return $this->hasMany('STS\Entities\TripDay','trip_id');
	}

	public function califications() {
        return $this->hasMany('STS\Entities\Calification','viajes_id');
    } 

	public function passengerCount() 
	{
		return $this->pasajeros()->count();
		//return ($viajeActual->total_seats - count($pasajeros));
    }

	public function disponibles() 
	{
		return $this->total_seats - $this->pasajeros()->count();
	}

	public function esConductor()
	{
		return $this->passenger()
		            ->where("request_state",Passenger::STATE_ACEPTADO)
					->where('passenger_type', '=', Passenger::TYPE_CONDUCTOR)
					->where("user_id",$this->user_id)->count() > 0;
	}


}
