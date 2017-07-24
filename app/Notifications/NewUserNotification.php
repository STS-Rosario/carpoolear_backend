<?php

namespace STS\Notifications;

use  STS\Services\Notifications\BaseNotification;
use  STS\Services\Notifications\Channels\MailChannel;

class NewUserNotification extends BaseNotification
{
    protected $via = [MailChannel::class];

    public $force_email = true;

    public function toEmail($user)
    {
        return [
            'title' => 'Bienvenido a Carpoolear',
            'email_view' => 'create_account',
            'url' => config('app.url').'/app/activate/'.$user->activation_token,
        ];
    }
}
