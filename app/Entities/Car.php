<?php

namespace STS\Entities;

use Illuminate\Database\Eloquent\Model;

class Car extends Model
{
    protected $table = 'cars';
    protected $fillable = ['patente', 'description', 'user_id'];
    protected $hidden = [];

    public function user()
    {
        return $this->belongsTo('STS\User', 'user_id');
    }
}
