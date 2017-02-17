<?php

namespace STS\Repository;

use STS\Contracts\Repository\User as UserRep;
use STS\User;

class UserRepository implements UserRep
{
    /**
     * Create a new user instance after a valid registration.
     *
     * @param  array  $data
     * @return User
     */
    public function create(array $data)
    {
        return User::create($data);
    }

    public function update($user, array $data)
    {
        return $user->update($data);
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

    public function index()
    {
        return User::all();
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
}
