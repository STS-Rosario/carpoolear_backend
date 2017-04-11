<?php

namespace STS\Listeners\Notification;

use STS\Events\Notification\NotificationSending;
use  STS\Services\Notifications\Channels\MailChannel;

class CanSendEmail
{
    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     *
     * @param  NotificationSending  $event
     * @return void
     */
    public function handle(NotificationSending $event)
    {
        if ($event->channel instanceof MailChannel) {
            if (! $event->user->emails_notifications) {
                if (! $event->notification->force_email) {
                    return false;
                }
            }
        }
    }
}
