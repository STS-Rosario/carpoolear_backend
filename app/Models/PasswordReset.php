<?php

namespace STS\Models;

use Illuminate\Database\Eloquent\Model;

class PasswordReset extends Model
{
    public $incrementing = false;

    public $timestamps = false;

    protected $table = 'password_resets';

    protected $fillable = [
        'email',
        'token',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }
}
