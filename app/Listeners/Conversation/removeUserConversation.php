<?php

namespace STS\Listeners\Conversation;

use STS\Events\Passenger\Cancel;
use STS\Models\User;
use STS\Repository\ConversationRepository;
use STS\Services\Logic\ConversationsManager;

class removeUserConversation
{
    protected $conversationRepo;

    protected $conversationLogic;

    public function __construct(ConversationRepository $logic, ConversationsManager $conversationLogic)
    {
        $this->conversationRepo = $logic;
        $this->conversationLogic = $conversationLogic;
    }

    public function handle(Cancel $event)
    {
        $converstion = $event->trip->conversation;
        if ($converstion) {
            $user = $event->trip->user_id == $event->from->id ? $event->to : $event->from;
            if ($user instanceof User) {
                $this->conversationLogic->sendSystemMessage(
                    $converstion->fresh(),
                    $user,
                    'notifications.group_chat_user_left',
                    ['name' => $user->name]
                );
            }
            $this->conversationRepo->removeUser($converstion, $user);
        }
    }
}
