<?php

namespace STS\Entities;

use Illuminate\Database\Eloquent\Model;

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

    protected $dates = [
        'created_at',
        'updated_at',
        'reply_comment_created_at',
        'rate_at',
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

    public function trip()
    {
        return $this->belongsTo('STS\Entities\Trip', 'trip_id');
    }
}
