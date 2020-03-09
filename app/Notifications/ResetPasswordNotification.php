<?php

namespace STS\Notifications;

use  STS\Services\Notifications\BaseNotification;
use  STS\Services\Notifications\Channels\MailChannel;

class ResetPasswordNotification extends BaseNotification
{
    protected $via = [MailChannel::class];

    public $force_email = true;

    public function toEmail($user)
    {
        return [
            'title' => 'Recuperación de contraseña de ' . config('carpoolear.name_app'),
            'email_view' => 'reset_password',
            'url' => config('app.url').'/app/reset-password/'.$this->getAttribute('token'),
            'name_app' => config('carpoolear.name_app'),
            'domain' => config('app.url')
        ];
    }
}
