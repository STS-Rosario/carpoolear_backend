<?php namespace STS\Entities;

use Illuminate\Database\Eloquent\Model;

class Calification extends Model {
    const STATE_RECHAZADO   = 0;
    const STATE_ACEPTADO    = 1; 

	protected $table = 'calificaciones';
	protected $fillable = [
		'viajes_id',
		'activo_id', 
		'pasivo_id', 
		'puntuacion',
		'descripcion',
		'viajo', 
		'tipo_pasajero',  
	];
	protected $hidden = [];

	public function activo() {
        return $this->belongsTo('STS\User','activo_id');
    }

    public function pasivo() {
        return $this->belongsTo('STS\User','pasivo_id');
    }

    public function trip() {
        return $this->belongsTo('STS\Entities\Trip','trip_id');
    }



}
