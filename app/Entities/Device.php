<?php

namespace STS\Entities;

use Illuminate\Database\Eloquent\Model;

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
    ];

    protected $hidden = [];

    protected $cast = [
        'notifications' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo('STS\User', 'user_id');
    }

    public function isAndroid()
    {
        return strpos($this->device_type, 'Android') !== false;
    }

    public function isIOS()
    {
        return strpos($this->device_type, 'iOS') !== false;
    }

    public function isBrowser()
    {
        return strpos($this->device_type, 'Browser') !== false;
    }
}
