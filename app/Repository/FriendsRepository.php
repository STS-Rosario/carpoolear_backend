<?php

namespace STS\Repository; 

use STS\Contracts\Repository\Friends as FriendsRepo;
use STS\User as UserModel;

class FriendsRepository implements FriendsRepo
{ 
    public function add(UserModel $user1, UserModel $user2, $state) {
        $user1->friends()->attach($user2->id, ['state' => $state]); 
    }

    public function delete(UserModel $user1, UserModel $user2) {
        $user1->friends()->detach($user2->id);
    }

    public function get(UserModel $user1, UserModel $user2, $state = null) {
        $friends = $user1->friends();
        if ($user2) {
            $friends->where("uid2", $user2->id);
        }
        if ($state) {
            $friends->where("state", $state);
        }
        return $friends->get();
    }
}

