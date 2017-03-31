<?php

namespace STS\Services\Notifications\Channels;

use Illuminate\Database\Eloquent\Model;
use STS\Services\Notifications\Models\ValueNotification;
use STS\Services\Notifications\Models\DatabaseNotification;

class DatabaseChannel
{
    public function __construct()
    {
    }

    public function send($notification, $user)
    {
        $n = new DatabaseNotification();
        $n->user_id = $user->id;
        $n->type = $notification->getType();
        $n->save();

        foreach ($notification->keys() as $key) {
            $value = $notification->getAttribute($key);
            if ($value) {
                $v = new ValueNotification();
                $v->key = $key;
                if ($value instanceof Model) {
                    $v->value()->associate($value);
                } else {
                    $v->value_text = $value;
                }
                $n->plain_values()->save($v);
            }
        }
    }
}
