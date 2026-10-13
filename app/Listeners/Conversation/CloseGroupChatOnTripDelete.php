<?php

namespace STS\Listeners\Conversation;

use STS\Events\Trip\Delete as DeleteEvent;
use STS\Models\Conversation;
use STS\Repository\ConversationRepository;

class CloseGroupChatOnTripDelete
{
    public function __construct(private ConversationRepository $conversationRepo) {}

    public function handle(DeleteEvent $event): void
    {
        $trip = $event->trip;
        if (! $trip) {
            return;
        }

        $conversation = Conversation::where('trip_id', $trip->id)
            ->where('type', Conversation::TYPE_TRIP_CONVERSATION)
            ->first();

        if ($conversation && ! $conversation->isClosed()) {
            $this->conversationRepo->close($conversation);
        }
    }
}