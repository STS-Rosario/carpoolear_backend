<?php

namespace STS\Entities;

use Illuminate\Database\Eloquent\Model;

class Device extends Model
{
    protected $table = 'users_devices';
    protected $fillable = ['device_id', 'device_type', 'session_id', 'user_id', 'app_version'];
    protected $hidden = [];

    public function user()
    {
        return $this->belongsTo('STS\User', 'user_id');
    }
}
