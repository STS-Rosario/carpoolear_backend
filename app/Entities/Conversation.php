<?php

namespace STS\Entities;

use Illuminate\Database\Eloquent\Model;

class Conversation extends Model
{
    protected $table = 'conversations';
    protected $fillable = [
        'user_id',
    ];
    protected $hidden = [];

    public function user()
    {
        return $this->belongsTo('STS\User', 'user_id');
    }
}
