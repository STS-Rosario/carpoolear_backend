<?php

namespace STS\Services\Notifications;

use Illuminate\Database\Eloquent\Model;

class BaseNotification
{
    protected $via = [];

    protected $attributes = [];

    protected $type;

    protected $manager = null;

    public function __construct()
    {
        $this->type = get_class($this);
        $this->manager = new NotificationServices;
    }

    public function getType()
    {
        return $this->type;
    }

    /**
     * Set a value tu notification.
     *
     * @param $key string  Name of attribute
     * @param $value string|Model  Value of attribute
     */
    public function setAttribute($key, $value)
    {
        $this->attributes[$key] = $value;
    }

    public function getAttribute($key, $default = null)
    {
        if (isset($this->attributes[$key])) {
            return $this->attributes[$key];
        }

        return $default;
    }

    public function getAttributes()
    {
        return $this->attributes;
    }

    public function keys()
    {
        return array_keys($this->attributes);
    }

    public function notify($users)
    {
        foreach ($this->via as $channel) {
            $this->manager->send($this, $users, $channel);
        }
    }
}
