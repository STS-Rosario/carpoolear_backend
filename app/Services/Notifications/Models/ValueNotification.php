<?php

namespace STS\Services\Notifications\Models;

use Illuminate\Database\Eloquent\Model;

class ValueNotification extends Model
{
    protected $table = 'notifications_params';
    protected $fillable = ['value_text', 'key', 'notification_id'];
    protected $hidden = []; 

    public function value()
    {
        return $this->morphTo();
    }
}
