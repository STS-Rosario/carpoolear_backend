<?php namespace STS\Entities;

use Illuminate\Database\Eloquent\Model;

class Conversation extends Model { 

	const TYPE_TRIP_CONVERSATION = 0;
	const TYPE_PRIVATE_CONVERSATION = 1;

	protected $table = 'conversations';
	protected $fillable = [
		'trip_id', 
	];
	protected $hidden = [];

	public function user() {
        return $this->belongsTo('STS\User','user_id');
    }

}
