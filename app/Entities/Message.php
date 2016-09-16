<?php namespace STS\Entities;

use Illuminate\Database\Eloquent\Model;

class Conversation extends Model { 
    const STATE_NOLEIDO = 0;
    const STATE_LEIDO = 1;


	protected $table = 'messages';
	protected $fillable = [
		'user_id', 
        'text',
        'estado',
        'conversation_id'
	];

	protected $hidden = [];

	public function user() {
        return $this->belongsTo('STS\User','activo_id');
    }

}
