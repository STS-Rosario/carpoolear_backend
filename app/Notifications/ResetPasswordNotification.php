<?php

namespace STS\Notifications;

use  STS\Services\Notifications\BaseNotification;
use  STS\Services\Notifications\Channels\DatabaseChannel;
use  STS\Services\Notifications\Channels\MailChannel;

class ResetPasswordNotification extends BaseNotification 
{
    protected $via = [MailChannel::class];

    public function toEmail($user)
    {
        return [
            'title' => "Password Reset",
            'email_view' => 'reset_password',
            'url' => 'http://www.carpoolear.com.ar/app#Reset/' . $this->getAttribute('token')
        ];
    }
}