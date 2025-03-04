<?php

namespace STS\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use STS\Models\User;

class References extends Model
{
    protected $table = 'users_references';

    protected $fillable = [
        'user_id_from',
        'user_id_to',
        'comment'
    ]; 

    protected $hidden = [];

    protected $appends = [
        'from'
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
