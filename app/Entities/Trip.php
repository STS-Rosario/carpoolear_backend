<?php namespace STS\Entities;

use Illuminate\Database\Eloquent\Model;

class Trip extends Model {
	const FINALIZADO 	= 0;
    const ACTIVO 		= 1;

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
		'tripscol'
	];
	protected $hidden = [];

	public function user() {
        return $this->belongsTo('STS\User','user_id');
    }


	public function passenger() {
        return $this->hasMany('App\Entities\Passenger','trip_id')->with("user");
    } 

	public function days() {
		return $this->hasMany('App\Entities\TripDay','trip_id');
	}


}
