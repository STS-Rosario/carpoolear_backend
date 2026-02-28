<?php

namespace STS\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Subscription extends Model
{
    use HasFactory;

    protected static function newFactory()
    {
        return \Database\Factories\SubscriptionFactory::new();
    }
    protected $table = 'subscriptions';

    protected $fillable = [
        'user_id', 'trip_date',
        'from_address', 'from_json_address', 'from_lat', 'from_lng', 'from_radio',
        'to_address', 'to_json_address', 'to_lat', 'to_lng', 'to_radio',
        'state', 'from_id', 'to_id', 'is_passenger'
    ];

    protected $hidden = ['created_at', 'updated_at'];

    protected function casts(): array
    {
        return [
            'to_json_address' => 'array',
            'from_json_address' => 'array',
            'trip_date' => 'datetime',
        ];
    } 
    
    public function user()
    {
        return $this->belongsTo('STS\Models\User', 'user_id');
    }

    public function setToLatAttribute($value)
    {
        $this->attributes['to_lat'] = $value;
        $this->attributes['to_sin_lat'] = sin(deg2rad($value));
        $this->attributes['to_cos_lat'] = cos(deg2rad($value));
    }

    public function setToLngAttribute($value)
    {
        $this->attributes['to_lng'] = $value;
        $this->attributes['to_sin_lng'] = sin(deg2rad($value));
        $this->attributes['to_cos_lng'] = cos(deg2rad($value));
    }

    public function setFromLatAttribute($value)
    {
        $this->attributes['from_lat'] = $value;
        $this->attributes['from_sin_lat'] = sin(deg2rad($value));
        $this->attributes['from_cos_lat'] = cos(deg2rad($value));
    }

    public function setFromLngAttribute($value)
    {
        $this->attributes['from_lng'] = $value;
        $this->attributes['from_sin_lng'] = sin(deg2rad($value));
        $this->attributes['from_cos_lng'] = cos(deg2rad($value));
    }
}
