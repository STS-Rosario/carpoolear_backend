<?php

namespace STS\Models;

use Illuminate\Database\Eloquent\Model;

class Donation extends Model
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

    protected function casts(): array
    {
        return [
            'has_donated' => 'boolean',
            'has_denied' => 'boolean',
        ];
    }  
}
