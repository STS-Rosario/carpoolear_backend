<?php

namespace STS\Listeners\Conversation;

use STS\Events\Passenger\Accept;
use STS\Services\TripGroupChatService;

class addUserConversation
{
    public function __construct(private TripGroupChatService $tripGroupChatService) {}

    public function handle(Accept $event)
    {
        if ($event->to) {
            $this->tripGroupChatService->syncOnPassengerAccept($event->trip, $event->to);
        }
    }
}
