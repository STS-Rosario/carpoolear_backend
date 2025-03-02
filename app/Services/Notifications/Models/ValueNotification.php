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
        // $this->value_type = str_replace('STS\User', 'STS\Models\User', $this->value_type);
        // $this->value_type = str_replace('Entities', 'Models', $this->value_type);
        if (strlen($this->value_type) > 0) {
            return $this->morphTo()->withTrashed();
        } else {
            return $this->morphTo();
        }
    }
}
