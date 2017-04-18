<?php

namespace STS\Listeners\Conversation;

use STS\Events\Trip\Create;
use STS\Contracts\Logic\Conversation as ConversationLogic;

class createConversation
{
    protected $conversationLogic;

    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct(ConversationLogic $logic)
    {
        $this->conversationLogic = $logic;
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
        $this->conversationLogic->createTripConversation($event->trip->id);
    }
}
