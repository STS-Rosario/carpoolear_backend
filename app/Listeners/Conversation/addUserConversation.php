<?php

namespace STS\Listeners\Conversation;

use STS\Events\Passenger\Accept;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use STS\Contracts\Repository\Conversations as ConversationRepo;

class addUserConversation
{
    protected $conversationRepo;

    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct(ConversationRepo $repo)
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
