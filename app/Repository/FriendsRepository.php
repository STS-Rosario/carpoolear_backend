<?php

namespace STS\Repository; 

use STS\Contracts\Repository\Friends as FriendsRepo;
use STS\User as UserModel;

class FriendsRepository implements FriendsRepo
{ 
    public function add(UserModel $user1, UserModel $user2, $state) {
        $user1->allFriends()->attach($user2->id, ['state' => $state]); 
    }

    public function delete(UserModel $user1, UserModel $user2) {
        $user1->allFriends()->detach($user2->id);
    }

    public function get(UserModel $user1, UserModel $user2 = null, $state = null) {
        $friends = $user1->allFriends($state);
        if ($user2) {
            $friends->where("id", $user2->id);
        }
        return $friends->get();
    }

    public function closestFriend(UserModel $user1, UserModel $user2 = null) {
        $friends = $user1->friends()
                         ->whereHas("friends", 
                            function ($q) use ($user2) {
                                $q->whereId($user2->id);
                            }
                         )->count(); 
                         
        return $friends > 0;
    }
}

