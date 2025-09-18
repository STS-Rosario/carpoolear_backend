<?php

namespace STS\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class Device extends Model
{
    protected $table = 'users_devices';

    protected $fillable = [
        'device_id',
        'device_type',
        'session_id',
        'user_id',
        'app_version',
        'notifications',
        "last_activity"
    ];

    protected $hidden = [];

    protected function casts(): array
    {
        return [
            'notifications' => 'boolean'
        ];
    } 

    public function getLastActivityAttribute($value)
    {
        return Carbon::parse($value);
    }

    public function user()
    {
        return $this->belongsTo('STS\Models\User', 'user_id');
    }

    public function isAndroid()
    {
        return strpos(strtolower($this->device_type), 'android') !== false;
    }

    public function isIOS()
    {
        return strpos(strtolower($this->device_type), 'ios') !== false;
    }

    public function isBrowser()
    {
        return strpos(strtolower($this->device_type), 'browser') !== false;
    }
}
