<?php

namespace STS\Listeners\Notification;

use STS\Notifications\NewMessageNotification;
use  STS\Events\Notification\NotificationSending;
use STS\Repository\ConversationRepository;
use  STS\Services\Notifications\Channels\MailChannel;
use STS\Services\Notifications\Channels\DatabaseChannel; 

class PreventMessageEmail
{
    protected $conversationsRepository;

    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct(ConversationRepository $cRepo)
    {
        $this->conversationsRepository = $cRepo;
    }

    /**
     * Handle the event.
     *
     * @param  NotificationSending  $event
     * @return void
     */
    public function handle(NotificationSending $event)
    {
        if ($event->channel instanceof MailChannel || $event->channel instanceof DatabaseChannel) {
            if ($event->notification instanceof NewMessageNotification) {
                $c = $event->notification->getAttribute('messages')->conversation;
                $u = $event->user;
                $readState = $this->conversationsRepository->getConversationReadState($c, $u);

                return  $readState != 1;
            }
        }
    }
}
