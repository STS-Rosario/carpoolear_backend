<?php

namespace STS\Models;

use STS\Models\User as UserModel;
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

    protected function casts(): array
    {
        return [
            'deleted_at' => 'boolean'
        ];
    } 
    protected $hidden = [];

    public function users()
    {
        return $this->belongsToMany('STS\Models\User', 'conversations_users', 'conversation_id', 'user_id')->withPivot('read')->withTimestamps();
    }

    public function read(UserModel $user)
    {
        return $this->users()->where('user_id', $user->id)->first()->pivot->read;
    }

    public function messages()
    {
        return $this->hasMany('STS\Models\Message', 'conversation_id');
    }
}
