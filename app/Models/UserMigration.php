<?php

namespace STS\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserMigration extends Model
{
    protected $table = 'user_migrations';

    protected $fillable = [
        'admin_user_id',
        'user_id_kept',
        'user_id_removed',
    ];

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_user_id');
    }
}
