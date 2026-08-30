<?php

namespace STS\Models;

use Illuminate\Database\Eloquent\Model;

class References extends Model
{
    protected $table = 'users_references';

    protected $fillable = [
        'user_id_from',
        'user_id_to',
        'comment',
        'reply_comment',
        'reply_comment_created_at',
    ];

    protected function casts(): array
    {
        return [
            'reply_comment_created_at' => 'datetime',
        ];
    }

    protected $hidden = [];

    protected $appends = [
        'from',
    ];

    public function from()
    {
        return $this->belongsTo('STS\Models\User', 'user_id_from');
    }

    public function to()
    {
        return $this->belongsTo('STS\Models\User', 'user_id_to');
    }

    public function getFromAttribute()
    {
        return User::find($this->user_id_from);
    }
}
