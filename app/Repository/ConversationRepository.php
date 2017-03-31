<?php

namespace STS\Repository;

use STS\Entities\Conversation;
use STS\Entities\Trip;
use STS\User;
use STS\Contracts\Repository\Conversations as ConversationRepo;
use Illuminate\Pagination\Paginator;

class ConversationRepository implements ConversationRepo {

    public function store (Conversation $conversation) {
        return $conversation->save();
    }

    public function delete (Conversation $conversation) {
        return $conversation->delete();
    }

    /* CONVERSATION GETTERS */

    public function getConversationsFromUser (User $user, $pageNumber, $pageSize) {
        $userConversations = $user->conversations()
            ->orderBy("updated_at","desc");
        return make_pagination($userConversations, $pageNumber, $pageSize);
    }

    public function getConversationFromId ( $conversation_id, User $user = null ) {
        $conversation = Conversation::where('id', $conversation_id)->first();
        if ($conversation == null) {
            return null; // el viaje no existe;
        }
        if ($user != null) {
            if ($conversation->users()->where('user_id', $user->id)->count() == 0) {
                return null; // handlear error
            }
        }
        return $conversation;
    }

    public function getConversationByTripId ( $tripId, User $user = null ) {
        $conversation = Conversation::where('trip_id', $tripId)->first();
        if ($user) {
            $conversation->users()->where('id', $user->id);
        }
        return $conversation->first();
    } 

    /* USERS CONTROLS */

    public function users (Conversation $conversation) {
        return $conversation->users;
    }

    public function addUser (Conversation $conversation, User $user) {
        $conversation->users()->attach($user->id, ['read' => true]);
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
    
    public function changeConversationReadState (Conversation $conversation, User $user, $read_state) {
        $conversation->users()->updateExistingPivot($user->id, ['read' => $read_state]);
    }
}