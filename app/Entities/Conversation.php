<?php namespace STS\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Conversation extends Model { 

	use SoftDeletes;

	const TYPE_PRIVATE_CONVERSATION = 0;
	const TYPE_TRIP_CONVERSATION 	= 1;

	protected $table = 'conversations';
	protected $fillable = [
		'type',
		'title',
		'trip_id', 
	];
	protected $dates = ['deleted_at'];

	protected $hidden = [];

	public function users() {
        return $this->belongsToMany('STS\User','conversations_users', 'conversation_id','user_id');
    }

	public function messages() {
        return $this->hasMany('STS\Entity\Message', 'conversation_id');
    }

}
