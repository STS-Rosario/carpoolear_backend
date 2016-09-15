<?php namespace STS\Entities;

use Illuminate\Database\Eloquent\Model;

class Device extends Model {
	protected $table = 'recurrent_trip_day';
	protected $fillable = ['day', 'hour','trip_id'];
	protected $hidden = [];

    public function trip() {
        return $this->belongsTo('STS\Entities\Trip');
    }
    
}