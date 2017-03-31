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
    ];
    protected $hidden = [];

    public function user_from()
    {
        return $this->belongsTo('STS\User', 'user_id_from');
    }

    public function user_to()
    {
        return $this->belongsTo('STS\User', 'user_id_to');
    }

    public function trip()
    {
        return $this->belongsTo('STS\Entities\Trip', 'trip_id');
    }
}
