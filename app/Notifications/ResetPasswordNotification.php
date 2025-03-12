<?php

namespace STS\Notifications;

use  STS\Services\Notifications\BaseNotification;
use  STS\Services\Notifications\Channels\MailChannel;

class ResetPasswordNotification extends BaseNotification
{
    protected $via = [
        MailChannel::class,
    ];

    public $force_email = true;

    public function toEmail($user)
    {
        $token = $this->getAttribute('token');
        $appName = config('carpoolear.name_app') ?: 'Carpoolear';

        return [
            'title' => 'Recuperación de contraseña de ' . $appName,
            'email_view' => 'reset_password',
            'name_app' => config('carpoolear.name_app'),
            'domain' => config('app.url'),
            'token' => $token ?: '',
            'url' => config('app.url').'/app/password/reset/'.($token ?: '')
        ];
    }

    public function toString()
    {
        return 'Recuperación de contraseña';
    }
}
