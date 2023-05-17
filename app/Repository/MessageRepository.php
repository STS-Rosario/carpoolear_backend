<?php

namespace STS\Repository;

use DB;
use STS\User;
use Carbon\Carbon;
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

    public function getMessages(Conversation $conversation, $timestamp, $pageSize)
    {
        $conversationMessages = $conversation->messages()->orderBy('created_at', 'desc');
        if ($timestamp) {
            $conversationMessages->where('created_at', '<', $timestamp);
        }

        $conversationMessages->take($pageSize);

        return $conversationMessages->get();
    }

    public function getUnreadMessages(Conversation $conversation, User $user)
    {
        return $conversation->messages()->whereHas('users', function ($q) use ($user) {
            $q->where('user_id', $user->id)
                ->where('read', false);
        })->orderBy('created_at', 'desc')->get();
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
        /* $msgs = Message::whereHas('users', function ($q) use ($user) {
            $q->where('user_id', $user->id)
                ->where('read', false);
        }); */

        $conversations = $user->conversations;

        $conversations_id = [];

        $conversations->each(function ($item, $key) use (&$conversations_id) {
            if ($item->pivot->read == 0) {
                $conversations_id[] = $item->id;
            }
        });

        $msgs = Message::whereIn('conversation_id', $conversations_id);

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
                    ->whereHas('users',
                        function ($q) use ($user) {
                            $q->where('user_id', $user->id)
                                ->where('read', false);
                        })
                    ->pluck('id');
        DB::table('user_message_read')
          ->whereIn('message_id', $msgs)
          ->where('user_id', $user->id)
          ->update([
              'read' => true,
              'updated_at' => Carbon::Now(),
          ]);
    }
}
