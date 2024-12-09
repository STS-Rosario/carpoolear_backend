<?php

namespace STS\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class Rating extends Model
{
    const STATE_NEGATIVO = 0;

    const STATE_POSITIVO = 1;

    const RATING_INTERVAL = 15;

    protected $table = 'rating';

    protected $fillable = [
        'trip_id',
        'user_id_from',
        'user_id_to',
        'rating',
        'comment',
        'reply_comment',
        'reply_comment_created_at',
        'user_to_type',
        'user_to_state',
        'voted',
        'voted_hash',
        'rate_at',
    ];

    protected function casts(): array
    {
        return [
            'reply_comment_created_at' => 'datetime',
            'rate_at' => 'datetime',
        ];
    } 

    protected $hidden = [];

    public function from()
    {
        return $this->belongsTo('STS\Models\User', 'user_id_from');
    }

    public function to()
    {
        return $this->belongsTo('STS\Models\User', 'user_id_to');
    }

    public function trip()
    {
        return $this->belongsTo('STS\Models\Trip', 'trip_id')->withoutGlobalScope(SoftDeletingScope::class);
    }
}
