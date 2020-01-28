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
        $userConversations = $user->conversations()->has('messages')
            ->orderBy('updated_at', 'desc')
            ->with('users');
        /*
        ->with(['messages' => function ($q) {
            $q->orderBy('created_at', 'DESC');
            $q->take(1);
        }]);
        */

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

    public function getConversationsByTrip($trip)
    {
        $conversations = Conversation::where('trip_id', $tripId)->get();

        return $conversations;
    }

    /* USERS CONTROLS */

    public function users(Conversation $conversation)
    {
        return $conversation->users;
    }

    public function addUser(Conversation $conversation, $userID)
    {
        $conversation->users()->attach($userID, ['read' => true]);
    }

    public function removeUser(Conversation $conversation, User $user)
    {
        $conversation->users()->detach($user->id);
    }

    public function matchUser($user1ID, $user2ID)
    {
        /* return Conversation::whereHas('users', function ($query) use ($user1ID) {
            $query->where('id', $user1ID);
        })->whereHas('users', function ($query) use ($user2ID) {
            $query->where('id', $user2ID);
        })->where('type', Conversation::TYPE_PRIVATE_CONVERSATION)->first(); */

        \Log::info('estamos aca');
        /*
        
        select * from `conversations` where exists (
            select * from `users` inner join `conversations_users` on `users`.`id` = `conversations_users`.`user_id` where `conversations_users`.`conversation_id` = `conversations`.`id` and `id` = '228229') 
            
            and exists (select * from `users` inner join `conversations_users` on `users`.`id` = `conversations_users`.`user_id` where `conversations_users`.`conversation_id` = `conversations`.`id` and `id` = '215497')

        and `type` = '0' and `conversations`.`deleted_at` is null limit 1
        */
        
        $query = Conversation::query();
        // leftJoin('t1 as staff_table', 'staff_table.id','=','t2.s_id')
        $query->join('conversations_users as c1', 'conversations.id', '=', 'c1.conversation_id');
        $query->join('conversations_users as c2', 'conversations.id', '=', 'c2.conversation_id');
    
        $query->where('c1.user_id', $user1ID);
        $query->where('c2.user_id', $user2ID);
    
        $query->where('conversations.type', Conversation::TYPE_PRIVATE_CONVERSATION);
        $query->whereNull('conversations.deleted_at');
        $query->select('conversations.*');
        return $query->first();
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

    public function updateTripId (Conversation $conversation, $tripId)
    {
        $conversation->trip_id = $tripId;
        $conversation->save();
        return $conversation;
    }

    public function userList($user, $who = null, $search_text = null)
    {
        $userConversations = $user->conversations()->has('messages')
        ->orderBy('updated_at', 'desc')
        ->with('users');

        $conversations = $userConversations->get();

        $users = [];
        foreach ($conversations as  $conversation) {
            foreach ($conversation->users as  $userc) {
                if ($userc->id !== $user->id) {
                    if (preg_match("/$search_text/i", $userc->name)) {
                        $users[] = $userc;
                    }
                }
            }
        }

        return collect($users);
    }

    public function usersToChat($userID, $whoID = null, $search_text = null)
    {
        $users = User::where(function ($q) use ($userID) {
            $q->where('is_admin', true);
            $q->orWhereHas('friends', function ($q) use ($userID) {
                $q->where('id', $userID);
            });
            $q->orWhereHas('trips', function ($q) use ($userID) {
                $q->where('friendship_type_id', Trip::PRIVACY_PUBLIC);
                $q->orWhere(function ($q) use ($userID) {
                    $q->whereFriendshipTypeId(Trip::PRIVACY_FOF);
                    $q->where(function ($q) use ($userID) {
                        $q->whereHas('user.friends', function ($q) use ($userID) {
                            $q->whereId($userID);
                        });
                        $q->orWhereHas('user.friends.friends', function ($q) use ($userID) {
                            $q->whereId($userID);
                        });
                    });
                });
                $q->orWhereHas('passengerAccepted', function ($q) use ($userID) {
                    $q->where('user_id', $userID);
                });
            });
            $q->orWhereHas('passenger.trip.user', function ($q) use ($userID) {
                $q->where('id', $userID);
            });
        });
        $users->where('id', '<>', $userID);
        if ($whoID) {
            $users->where('id', $whoID);
        }
        if ($search_text) {
            $users->where(function ($q) use ($search_text) {
                $q->where('name', 'like', '%'.$search_text.'%');
                // $q->orWhere('email', 'like', '%'.$search_text.'%');
            });
        }
        $users->with('accounts');
        $users->orderBy('name');

        return $users->get();
    }
}
