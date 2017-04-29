<?php

namespace STS\Repository;

use STS\User;
use STS\Entities\Trip;
use STS\Entities\Conversation;
use STS\Contracts\Repository\Conversations as ConversationRepo;

class ConversationRepository implements ConversationRepo
{
    public function store(Conversation $conversation)
    {
        return $conversation->save();
    }

    public function delete(Conversation $conversation)
    {
        return $conversation->delete();
    }

    /* CONVERSATION GETTERS */

    public function getConversationsFromUser(User $user, $pageNumber, $pageSize)
    {
        $userConversations = $user->conversations()
            ->orderBy('updated_at', 'desc');

        return make_pagination($userConversations, $pageNumber, $pageSize);
    }

    public function getConversationFromId($conversation_id, User $user = null)
    {
        $conversation = Conversation::where('id', $conversation_id)->first();
        if ($conversation == null) {
            return; // el viaje no existe;
        }
        if ($user != null) {
            if ($conversation->users()->where('user_id', $user->id)->count() == 0) {
                return; // handlear error
            }
        }

        return $conversation;
    }

    public function getConversationByTripId($tripId, User $user = null)
    {
        $conversation = Conversation::where('trip_id', $tripId)->first();
        if ($user) {
            $conversation->users()->where('id', $user->id);
        }

        return $conversation->first();
    }

    /* USERS CONTROLS */

    public function users(Conversation $conversation)
    {
        return $conversation->users;
    }

    public function addUser(Conversation $conversation, User $user)
    {
        $conversation->users()->attach($user->id, ['read' => true]);
    }

    public function removeUser(Conversation $conversation, User $user)
    {
        $conversation->users()->detach($user->id);
    }

    public function matchUser(User $user1, User $user2)
    {
        return Conversation::whereHas('users', function ($query) use ($user1) {
            $query->where('id', $user1->id);
        })->whereHas('users', function ($query) use ($user2) {
            $query->where('id', $user2->id);
        })->where('type', Conversation::TYPE_PRIVATE_CONVERSATION)->first();
    }

    public function changeConversationReadState(Conversation $conversation, User $user, $read_state)
    {
        $conversation->users()->updateExistingPivot($user->id, ['read' => $read_state]);
    }

    public function getConversationReadState(Conversation $conversation, User $user)
    {
        $u = $conversation->users()->where('id', $user->id)->first();

        return $u->pivot->read;
    }

    public function userList($user, $who = null, $search_text = null)
    {
        $users = User::where(function ($q) use ($user) {
            $q->where('is_admin', true);
            $q->orWhereHas('friends', function ($q) use ($user) {
                $q->where('id', $user->id);
            });
            $q->orWhereHas('trips', function ($q) use ($user) {
                $q->where('friendship_type_id', Trip::PRIVACY_PUBLIC);
                $q->orWhere(function ($q) use ($user) {
                    $q->whereFriendshipTypeId(Trip::PRIVACY_FOF);
                    $q->orWhere(function ($q) use ($user) {
                        $q->whereFriendshipTypeId(Trip::PRIVACY_FRIENDS);
                        $q->whereHas('user.friends', function ($q) use ($user) {
                            $q->whereId($user->id);
                        });
                    });
                    $q->where(function ($q) use ($user) {
                        $q->whereHas('user.friends', function ($q) use ($user) {
                            $q->whereId($user->id);
                        });
                        $q->orWhereHas('user.friends.friends', function ($q) use ($user) {
                            $q->whereId($user->id);
                        });
                    });
                });
            });
        });

        if ($who) {
            $users->where('id', $who->id);
        }
        if ($search_text) {
            $users->where(function ($q) use ($search_text) {
                $q->where('name', 'like', '%'.$search_text.'%');
                $q->orWhere('email', 'like', '%'.$search_text.'%');
            });
        }
        $users->orderBy('name');

        return $users->get();
    }
}
