<?php

namespace STS\Notifications;

use STS\Services\Notifications\BaseNotification;
use STS\Services\Notifications\Channels\DatabaseChannel;
use STS\Services\Notifications\Channels\MailChannel;

class DummyNotification extends BaseNotification
{
    public function __construct()
    {
        parent::__construct();
        $this->via = [
            DatabaseChannel::class,
            MailChannel::class,
        ];
    }

    /*

    Send Email althoug email_notifications is false
    public $force_email = true;

    */

    public function toEmail($user)
    {
        return [
            'title' => 'Dummy Title',
            'email_view' => 'dummy',
        ];
    }

    public function toString()
    {
        return 'Dummy Notification '.$this->getAttribute('dummy');
    }

    public function getExtras()
    {
        return [
        ];
    }
}
