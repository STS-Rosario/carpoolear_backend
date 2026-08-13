<?php

namespace STS\Repository;

use Carbon\Carbon;
use DB;
use STS\Models\Conversation;
use STS\Models\Message;
use STS\Models\User;

class MessageRepository
{
    public function store(Message $message)
    {
        return $message->save();
    }

    public function delete(Message $message)
    {
        return $message->delete();
    }

    public function getMessages(Conversation $conversation, $timestamp, $pageSize)
    {
        $conversationMessages = $conversation->messages()
            ->orderBy('created_at', 'desc')
            ->orderBy('id', 'desc');
        if ($timestamp) {
            $conversationMessages->where('created_at', '<', $timestamp);
        }

        $conversationMessages->take($pageSize);

        return $conversationMessages->get();
    }

    public function getUnreadMessages(Conversation $conversation, User $user)
    {
        return $conversation->messages()
            ->whereHas('users', $this->unreadForUserConstraint($user))
            ->orderBy('created_at', 'desc')
            ->orderBy('id', 'desc')
            ->get();
    }

    public function countConversationsWithUnreadMessages(User $user): int
    {
        return (int) $this->queryUnreadMessagesForUser($user)
            ->distinct()
            ->count('conversation_id');
    }

    public function changeMessageReadState(Message $message, User $user, $read_state)
    {
        $message->users()->updateExistingPivot($user->id, ['read' => $read_state]);
    }

    public function createMessageReadState(Message $message, User $user, $read_state)
    {
        $message->users()->attach($user->id, ['read' => $read_state]);
    }

    public function getMessagesUnread(User $user, $timestamp)
    {
        $msgs = $this->queryUnreadMessagesForUser($user);

        if ($timestamp) {
            $msgs->where('created_at', '>', $timestamp);
        }

        return $msgs->orderBy('conversation_id')
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function markMessages(User $user, $conversation_id)
    {
        $msgs = Message::where('conversation_id', $conversation_id)
            ->whereHas('users', $this->unreadForUserConstraint($user))
            ->pluck('id');
        DB::table('user_message_read')
            ->whereIn('message_id', $msgs)
            ->where('user_id', $user->id)
            ->update([
                'read' => true,
                'updated_at' => Carbon::Now(),
            ]);
    }

    private function queryUnreadMessagesForUser(User $user)
    {
        return Message::query()->whereHas('users', $this->unreadForUserConstraint($user));
    }

    private function unreadForUserConstraint(User $user): \Closure
    {
        return function ($q) use ($user) {
            $q->where('user_id', $user->id)
                ->where('read', false);
        };
    }
}
