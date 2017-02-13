<?php

namespace STS\Repository;

use STS\Entities\Conversation;
use STS\Entities\Trip;
use STS\User;

class conversationRepository {

    public function store (Conversation $conversation) {
        return $conversation->save();
    }

    public function delete (Message $conversation) {
        return $conversation->delete();
    }

    /* CONVERSATION GETTERS */

    public function getConversationsFromUser (User $user) {
        return  $user->conversations()->get();
    }

    public function getConversationFromId ( $conversation_id, User $user = null ) {
        $conversation = Conversation::where('id', $conversation_id);
        if ($user) {
            $conversation->users()->where('id', $user_id);
        }
        return $conversation->first();
    }

    public function getConversationByTripId ( $tripId, User $user = null ) {
        $conversation = Conversation::where('trip_id', $trip_id);
        if ($user) {
            $conversation->users()->where('id', $user_id);
        }
        return $conversation->first();
    } 

    /* USERS CONTROLS */

    public function users (Conversation $conversation) {
        return $conversation->users;
    }

    public function addUser (Conversation $conversation, User $user) {
        $conversation->users()->attach($user->id);
    }

    public function removeUser (Conversation $conversation, User $user) {
        $conversation->users()->detach($user->id);
    }

    public function matchUser(User $user1, User $user2) {
        return Conversation::whereHas('users', function ($query) use ($user1) {
            $query->where('id', $user1->id);
        })->whereHas('users', function ($query) use ($user2) {
            $query->where('id', $user2->id);
        })->where("type", Conversation::TYPE_PRIVATE_CONVERSATION)->first();
    }
}