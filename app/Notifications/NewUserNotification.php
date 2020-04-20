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
        \Log::info('NewUserNotification toEmail' . config('carpoolear.name_app'));
        return [
            'title' => 'Bienvenido a ' . config('carpoolear.name_app') . '!',
            'email_view' => 'create_account',
            'url' => config('app.url').'/app/activate/'.$user->activation_token,
            'name_app' => config('carpoolear.name_app'),
            'domain' => config('app.url')
        ];
    }
}
