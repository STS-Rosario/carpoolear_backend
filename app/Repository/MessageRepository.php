<?php

namespace STS\Repository;

use STS\User;
use STS\Entities\Message;
use STS\Entities\Conversation;
use STS\Contracts\Repository\Messages as MessageRepo;

class MessageRepository implements MessageRepo
{
    public function store(Message $message)
    {
        return $message->save();
    }

    public function delete(Message $message)
    {
        return $message->delete();
    }

    public function getMessages(Conversation $conversation, $pageNumber, $pageSize)
    {
        $conversationMessages = $conversation->messages()->orderBy('updated_at', 'desc');

        return make_pagination($conversationMessages, $pageNumber, $pageSize);
    }

    public function getUnreadMessages(Conversation $conversation, User $user)
    {
        return $conversation->messages()->whereHas('users', function ($q) use ($user) {
            $q->where('user_id', $user->id)
                ->where('read', false);
        })->orderBy('updated_at', 'desc')->get();
    }

    public function changeMessageReadState(Message $message, User $user, $read_state)
    {
        $message->users()->updateExistingPivot($user->id, ['read' => $read_state]);
    }

    public function createMessageReadState(Message $message, User $user, $read_state)
    {
        $message->users()->attach($user->id, ['read' => $read_state]);
    }
}
