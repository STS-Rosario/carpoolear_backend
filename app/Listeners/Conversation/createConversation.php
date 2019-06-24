<?php

namespace STS\Listeners\Conversation;

use STS\Events\Trip\Create;
use STS\Contracts\Logic\Conversation as ConversationLogic;
use STS\Contracts\Repository\Conversations as ConversationsRepo;

class createConversation
{
    protected $conversationLogic;

    protected $repoConv;

    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct(ConversationLogic $logic, ConversationsRepo $repo)
    {
        $this->conversationLogic = $logic;
        $this->repoConv = $repo;
    }

    /**
     * Handle the event.
     *
     * @param  Create  $event
     * @return void
     */
    public function handle(Create $event)
    {
        $trip = $event->trip;
        $c = $this->conversationLogic->createTripConversation($event->trip->id);
        $this->repoConv->addUser($c, $trip->user);
    }
}
