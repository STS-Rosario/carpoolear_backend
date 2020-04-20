<?php

namespace STS\Services\Notifications;

use Event;
use Illuminate\Support\Collection;
use STS\Events\Notification\NotificationSending;

class NotificationServices
{
    public function __construct()
    {
    }

    public function driver($name)
    {
        return new $name;
    }

    public function send($notification, $users, $channel)
    {
        // \Log::info('NotificationServices send');
        // FIXME ??? no config data on sending

        $settings = \STS\Entities\AppConfig::all();
        foreach ($settings as $config) {
            if (isset($config->is_laravel) && $config->is_laravel) {
                \Config::set($config->key, $config->value);
            } else {
                \Config::set("carpoolear." . $config->key, $config->value);
            }
        }
        /// -----------
        $users = (is_array($users) || $users instanceof Collection) ? $users : [$users];
        $driver = $this->driver($channel);
        foreach ($users as $user) {
            if ($this->shouldSendNotification($notification, $user, $driver)) {
                try {
                    $driver->send($notification, $user);
                } catch (\Exception $ex) {
                    \Log::info('error sending:');
                    \Log::info($ex);

                }
            }
        }
    }

    protected function shouldSendNotification($notification, $user, $channel)
    {
        return Event::until(
            new NotificationSending($notification, $user, $channel)
        ) !== false;
    }
}
