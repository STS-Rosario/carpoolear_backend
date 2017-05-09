<?php

namespace STS\Repository;

use DB;
use STS\User;
use Carbon\Carbon;
use STS\Contracts\Repository\User as UserRep;

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
        return User::find($id);
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
                $q->orWhere('email', 'like', '%'.$search_text.'%');
            });
        }

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

        return;
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
}
