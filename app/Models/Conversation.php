<?php

namespace STS\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use STS\Models\User as UserModel;

class Conversation extends Model
{
    use HasFactory, SoftDeletes;

    protected static function newFactory()
    {
        return \Database\Factories\ConversationFactory::new();
    }

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
            'deleted_at' => 'boolean',
        ];
    }

    protected $hidden = [];

    public function users()
    {
        return $this->belongsToMany('STS\Models\User', 'conversations_users', 'conversation_id', 'user_id')->withPivot('read')->withTimestamps();
    }

    public function read(UserModel $user)
    {
        $userRelation = $this->users()->whereKey($user->id)->first();

        return $userRelation ? $userRelation->pivot->read : false;
    }

    public function messages()
    {
        return $this->hasMany('STS\Models\Message', 'conversation_id');
    }
}
