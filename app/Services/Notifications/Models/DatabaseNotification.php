<?php

namespace STS\Services\Notifications\Models;

use Illuminate\Database\Eloquent\Model;
use STS\User;
use STS\Services\Notifications\Collections\NotificationCollection;

class DatabaseNotification extends Model
{
    protected $table = 'notifications';

    protected $fillable = ['user_id', 'type', 'read_at'];

    protected $hidden = [];

    protected $via = [];

    protected $_attributes = null;

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function plain_values()
    {
        return $this->hasMany(ValueNotification::class, 'notification_id')->with("value");
    }

    public function attributes()
    {
        // [MEJORA] $_attributes=new stdClass();
        if ($this->_attributes) {

            return $this->_attributes;
        }

        $this->_attributes = [];
        $plains_values = $this->plain_values;
        foreach ($plains_values as $plain) {
            if ($model = $plain->value) {
                $this->_attributes[$plain->key] = $model;
            } else {
                $this->_attributes[$plain->key] = $plain->value_text;
            }
        }

        return $this->_attributes;
    }

    public function asNotification()
    {
        $type = new $this->type;
        foreach($this->attributes() as $key => $value) {
            $type->setAttribute($key, $value);
        }
        return $type; 
    }

    public function readed()
    {
        $this->read_at = Carbon::now();
        $this->save();
    }

    public function newCollection(array $models = Array())
    { 
        return new NotificationCollection($models);
    }

}
