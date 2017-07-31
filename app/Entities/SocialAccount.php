<?php

namespace STS\Entities;

use Illuminate\Database\Eloquent\Model;
use STS\User;

class SocialAccount extends Model
{
    protected $fillable = ['user_id', 'provider_user_id', 'provider'];

    public function user()
    {
        return $this->belongsTo('STS\User');
    }
}
