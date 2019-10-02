<?php

namespace STS\Entities;

use Illuminate\Database\Eloquent\Model;

class Route extends Model
{
    protected $table = 'routes';

    protected $fillable = [
        'id',
        'from_id',
        'to_id',
    ];

    protected $hidden = [];

}
