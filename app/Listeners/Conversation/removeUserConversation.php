<?php

namespace STS\Listeners\Conversation;

use STS\Events\Passenger\Cancel;
use STS\Contracts\Repository\Conversations as ConversationRepo;

class removeUserConversation
{
    protected $conversationRepo;

    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct(ConversationRepo $logic)
    {
        $this->conversationRepo = $logic;
    }

    /**
     * Handle the event.
     *
     * @param  Cancel  $event
     * @return void
     */
    public function handle(Cancel $event)
    {
        $converstion = $event->trip->conversation;
        if ($converstion) {
            $user = $event->trip->user_id == $event->from->id ? $event->to : $event->from;
            $this->conversationRepo->removeUser($converstion, $user);
        }
    }
}
