<?php

namespace STS\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use STS\User;

class References extends Model
{
    protected $table = 'users_references';

    protected $fillable = [
        'user_id_from',
        'user_id_to',
        'comment'
    ];

    protected $dates = [
        'created_at',
        'updated_at'
    ];

    protected $hidden = [];

    protected $appends = [
        'from'
    ];

    public function from()
    {
        return $this->belongsTo('STS\User', 'user_id_from');
    }

    public function to()
    {
        return $this->belongsTo('STS\User', 'user_id_to');
    }

    public function getFromAttribute()
    {
        return User::find($this->user_id_from);
    }
}
