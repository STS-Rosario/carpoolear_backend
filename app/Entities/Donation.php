<?php

namespace STS\Entities;

use Illuminate\Database\Eloquent\Model;

class Device extends Model
{
    protected $table = 'donations';
    protected $fillable = [
        'user_id',
        'month',
        'has_donated',
        'has_denied',
        'ammount',
    ];

    protected $hidden = [];

    protected $cast = [
        'has_donated' => 'boolean',
        'has_denied' => 'boolean',
    ];

}
