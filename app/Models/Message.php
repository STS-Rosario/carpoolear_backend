<?php

namespace STS\Models;

use Illuminate\Database\Eloquent\Model;

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
        'time',
    ];

    protected $touches = ['conversation'];

    protected $casts = [
        'user_id' => 'integer',
        'conversation_id' => 'integer',
        'created_at' => 'datetime',
    ];

    public function conversation()
    {
        return $this->belongsTo('STS\Models\Conversation', 'conversation_id');
    }

    protected $hidden = [];

    public function from()
    {
        return $this->belongsTo('STS\Models\User', 'user_id');
    }

    public function users()
    {
        return $this->belongsToMany('STS\Models\User', 'user_message_read', 'message_id', 'user_id')->withPivot('read');
    }

    public function read(User $user)
    {
        return $this->users()->where('user_id', $user->id)->first()->pivot->read;
    }

    public function numberOfRead()
    {
        return $this->users()->where('read', true)->count() + 1;
    }
}
