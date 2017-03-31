<?php

namespace STS\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Message extends Model 
{ 
    const STATE_NOLEIDO = 0;
    const STATE_LEIDO = 1;

	protected $table = 'messages';
	protected $fillable = [
        'id',
		'user_id', 
        'text',
        'estado',
        'conversation_id',
        'time'
	];

    protected $touches = ['conversation'];

    public function conversation()
    {
        return $this->belongsTo('STS\Entities\Conversation','conversation_id');
    }

	protected $hidden = [];

	public function from() 
    {
        return $this->belongsTo('STS\User','user_id');
    }

    public function users() 
    {
        return $this->belongsToMany('STS\User','user_message_read', 'message_id','user_id')->withPivot('read');
    }

    public function read(User $user) 
    {
        return $this->users()->where('user_id', $user->id)->first()->pivot->read;
    }

}
