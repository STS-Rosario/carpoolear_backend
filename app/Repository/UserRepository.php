<?php

namespace STS\Repository;

use DB;
use STS\User;
use Carbon\Carbon;
use STS\Contracts\Repository\User as UserRep;
use STS\Entities\Trip;
use STS\Entities\Passenger;
use STS\Entities\Conversation;

class UserRepository implements UserRep
{
    /**
     * Create a new user instance after a valid registration.
     *
     * @param array $data
     *
     * @return User
     */
    public function create(array $data)
    {
        return User::create($data);
    }

    public function update($user, array $data)
    {
        $user->update($data);
    }

    public function show($id)
    {
        $user =User::with(['accounts', 'donations', 'referencesReceived'])->where('id', $id)->first(); 
        // prevent from returning the private_note to the frontend
        $user->private_note = null; // TODO: how to better hide this?
        return $user;
    }

    public function acceptTerms($user)
    {
        $user->terms_and_conditions = true;
        $user->save();

        return $user;
    }

    public function updatePhoto($user, $filename)
    {
        $user->image = $filename;
        $user->save();

        return $user;
    }

    public function getUserBy($key, $value)
    {
        return User::where($key, $value)->first();
    }
    public function searchUsers($name)
    {

        if ($name) {
            $users = User::where('name', 'like', '%'.$name.'%');
            $users->orWhere('email', 'like', '%'.$name.'%');
            $users->orWhere('nro_doc', 'like', '%'.$name.'%');
            $users->orWhere('mobile_phone', 'like', '%'.$name.'%');
        } else {
            return null;
        }
        $users->with('accounts');
        $users->orderBy('name');
        $users->limit(9);
        $users = $users->get();

        return $users;
    }

    public function index($user, $search_text = null)
    {
        $users = User::where('active', true)
                     ->where('banned', false)
                     ->where('id', '<>', $user->id);

        $users->whereDoesntHave('friends', function ($q) use ($user) {
            $q->where('id', $user->id);
        });

        if ($search_text) {
            $users->where(function ($q) use ($search_text) {
                $q->where('name', 'like', '%'.$search_text.'%');
                // $q->orWhere('email', 'like', '%'.$search_text.'%');
            });
        }

        $users->with('accounts');
        $users->orderBy('name');
        $users = $users->get();

        $users = $users->map(function ($item, $key) use ($user) {
            $u = $user->allFriends()->withPivot('state')->where('id', $item->id)->first();
            if ($u) {
                if ($u->pivot->state == User::FRIEND_REQUEST) {
                    $item->state = 'request';
                } else {
                    $item->state = 'friend';
                }
            } else {
                $item->state = 'none';
            }

            return $item;
        });

        return $users;
    }

    public function addFriend($user, $friend, $provider = '')
    {
        $friend->friends()->detach($user->id);
        $user->friends()->detach($friend->id);
        $friend->friends()->attach($user->id, ['origin' => $provider, 'state' => User::FRIEND_ACCEPTED]);
        $user->friends()->attach($friend->id, ['origin' => $provider, 'state' => User::FRIEND_ACCEPTED]);
    }

    public function deleteFriend($user, $friend)
    {
        $friend->friends()->detach($user->id);
        $user->friends()->detach($friend->id);
    }

    public function friendList($user)
    {
        $user->friends();
    }

    public function storeResetToken($user, $token)
    {
        DB::table('password_resets')->insert(
            ['email' => $user->email, 'token' => $token, 'created_at' => Carbon::now()]
        );
    }

    public function deleteResetToken($key, $value)
    {
        DB::table('password_resets')->where($key, $value)->delete();
    }

    public function getUserByResetToken($token)
    {
        $pr = DB::table('password_resets')->where('token', $token)->first();
        if ($pr) {
            return User::where('email', $pr->email)->first();
        }
    }

    public function getNotifications($user, $unread = false)
    {
        if (! $readed) {
            return $user->notifications;
        } else {
            return $user->unreadNotifications;
        }
    }

    public function markNotification($notification)
    {
        $notification->readed();
    }


    public function unansweredConversationOrRequestsByTrip ($userId, $tripId) {
        // todas las request que pertenezcan a un viaje mio y que esten pendientes
        $pendingRequests = Passenger::with('trip')
            ->where('trip_id', $tripId)
            ->whereHas('trip', function ($q) use ($userId) {
                $q->where('user_id', $userId);
            })
            ->where('request_state', Passenger::STATE_PENDING)
            ->count();

        // conversaciones que no tegan mensajes mios (respuestas)
        $unasweredConversations = Conversation::where('trip_id', $tripId)
            ->whereDoesntHave('messages', function ($query) use ($userId) {
                $query->where('user_id', $userId);
            })
            ->count();
        
        return $pendingRequests + $unasweredConversations;
    }
}
