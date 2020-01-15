<?php

namespace STS\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class TripVisibility extends Model
{
    protected $table = 'user_visibility_trip';
    protected $fillable = ['user_id', 'trip_id'];
    protected $hidden = [];

    protected function setKeysForSaveQuery(Builder $query)
    {
        $query
            ->where('user_id', '=', $this->getAttribute('user_id'))
            ->where('trip_id', '=', $this->getAttribute('trip_id'));
        return $query;
    }

    public function trip()
    {
        return $this->belongsTo('STS\Entities\Trip', 'trip_id');
    }

    public function user()
    {
        return $this->belongsTo('STS\User', 'user_id');
    }
}
