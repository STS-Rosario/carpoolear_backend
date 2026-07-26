<?php

namespace STS\Listeners\Conversation;

use STS\Events\Trip\Create;
use STS\Repository\ConversationRepository;
use STS\Services\Logic\ConversationsManager;

class createConversation
{
    protected $conversationLogic;

    protected $repoConv;

    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct(ConversationsManager $logic, ConversationRepository $repo)
    {
        $this->conversationLogic = $logic;
        $this->repoConv = $repo;
    }

    /**
     * Handle the event.
     *
     * @return void
     */
    public function handle(Create $event)
    {
        // Trip group chats are created once the trip reaches three participants.
    }
}
