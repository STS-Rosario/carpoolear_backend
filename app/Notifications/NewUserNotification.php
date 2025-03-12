<?php

namespace STS\Notifications;

use  STS\Services\Notifications\BaseNotification;
use  STS\Services\Notifications\Channels\MailChannel;
use  STS\Services\Notifications\Channels\DatabaseChannel;

class NewUserNotification extends BaseNotification
{
    protected $via = [
        MailChannel::class,
    ];

    public $force_email = true;
    
    public function toEmail($user)
    {
        $from = $this->getAttribute('from');
        $token = $this->getAttribute('token');

        return [
            'title' => '¡Bienvenido a Carpoolear!',
            'email_view' => 'create_account',
            'name_app' => config('carpoolear.name_app'),
            'domain' => config('app.url'),
            'url' => config('app.url').'/app/activate/'.($token ?: '')
        ];
    }

    public function toString()
    {
        return '¡Bienvenido a Carpoolear!';
    }
}
