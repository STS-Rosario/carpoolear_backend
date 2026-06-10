<?php

namespace STS\Listeners\Conversation;

use STS\Events\Passenger\Accept;
use STS\Repository\ConversationRepository;
use STS\Services\Logic\ConversationsManager;

class addUserConversation
{
    protected $conversationRepo;

    protected $conversationLogic;

    public function __construct(ConversationRepository $repo, ConversationsManager $logic)
    {
        $this->conversationRepo = $repo;
        $this->conversationLogic = $logic;
    }

    public function handle(Accept $event)
    {
        $converstion = $event->trip->conversation;
        if ($converstion) {
            $this->conversationRepo->addUser($converstion, $event->to);
            $subject = is_object($event->to) ? $event->to : \STS\Models\User::find($event->to);
            if ($subject) {
                $this->conversationLogic->sendSystemMessage(
                    $converstion->fresh(),
                    $subject,
                    'notifications.group_chat_user_joined',
                    ['name' => $subject->name]
                );
            }
        }
    }
}
