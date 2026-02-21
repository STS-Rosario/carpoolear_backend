<?php

namespace STS\Models;

use Illuminate\Database\Eloquent\Model;

class BannedUser extends Model
{
    protected $table = 'banned_users';

    protected $fillable = [
        'user_id',
        'nro_doc',
        'banned_at',
        'banned_by',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'banned_at' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
