<?php

namespace STS\Models;

use Illuminate\Database\Eloquent\Model;

class TripSearch extends Model
{
    protected $table = 'trip_searches';

    protected $fillable = [
        'user_id',
        'origin_id',
        'destination_id',
        'search_date',
        'amount_trips',
        'amount_trips_carpooleados',
        'client_platform',
        'is_passenger',
        'results_json'
    ];

    protected $casts = [
        'search_date' => 'datetime',
        'results_json' => 'array',
        'client_platform' => 'integer',
        'is_passenger' => 'boolean'
    ];

    // Client platform constants
    const PLATFORM_WEB = 0;
    const PLATFORM_IOS = 1;
    const PLATFORM_ANDROID = 2;

    public function user()
    {
        return $this->belongsTo('STS\Models\User', 'user_id');
    }

    public function origin()
    {
        return $this->belongsTo('STS\Models\NodeGeo', 'origin_id');
    }

    public function destination()
    {
        return $this->belongsTo('STS\Models\NodeGeo', 'destination_id');
    }
} 