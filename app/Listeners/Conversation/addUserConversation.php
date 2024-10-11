<?php

namespace STS\Listeners\Conversation;

use STS\Events\Passenger\Accept;
use STS\Repository\ConversationRepository; 

class addUserConversation
{
    protected $conversationRepo;

    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct(ConversationRepository $repo)
    {
        $this->conversationRepo = $repo;
    }

    /**
     * Handle the event.
     *
     * @param  Accept  $event
     * @return void
     */
    public function handle(Accept $event)
    {
        $converstion = $event->trip->conversation;
        if ($converstion) {
            $this->conversationRepo->addUser($converstion, $event->to);
        }
    }
}
