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
            'title' => 'Password Reset',
            'email_view' => 'reset_password',
            'url' => config('app.url').'/app/reset-password/'.$this->getAttribute('token'),
        ];
    }
}
