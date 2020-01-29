<?php

namespace STS\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class Referencias extends Model
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

    public function from()
    {
        return $this->belongsTo('STS\User', 'user_id_from');
    }

    public function to()
    {
        return $this->belongsTo('STS\User', 'user_id_to');
    }
}
