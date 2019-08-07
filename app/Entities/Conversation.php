<?php

namespace STS\Entities;

use STS\User as UserModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Conversation extends Model
{
    use SoftDeletes;

    const TYPE_PRIVATE_CONVERSATION = 0;

    const TYPE_TRIP_CONVERSATION = 1;

    protected $table = 'conversations';

    protected $fillable = [
        'type',
        'title',
        'trip_id',
    ];

    protected $dates = ['created_at', 'updated_at', 'deleted_at'];

    protected $hidden = [];

    public function users()
    {
        return $this->belongsToMany('STS\User', 'conversations_users', 'conversation_id', 'user_id')->withPivot('read')->withTimestamps();
    }

    public function read(UserModel $user)
    {
        return $this->users()->where('user_id', $user->id)->first()->pivot->read;
    }

    public function messages()
    {
        return $this->hasMany('STS\Entities\Message', 'conversation_id');
    }
}
