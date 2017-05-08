<?php

namespace STS\Repository;

use STS\User as UserModel;
use STS\Contracts\Repository\Friends as FriendsRepo;

class FriendsRepository implements FriendsRepo
{
    public function add(UserModel $user1, UserModel $user2, $state)
    {
        $user1->allFriends()->attach($user2->id, ['state' => $state]);
    }

    public function delete(UserModel $user1, UserModel $user2)
    {
        $user1->allFriends()->detach($user2->id);
    }

    public function get(UserModel $user1, UserModel $user2 = null, $state = null, $data = [])
    {
        $pageNumber = isset($data['page']) ? $data['page'] : null;
        $pageSize = isset($data['page_size']) ? $data['page_size'] : null;

        $friends = $user1->allFriends($state);
        if ($user2) {
            $friends->where('id', $user2->id);
        }

        if (isset($data['value'])) {
            $search_text = $data['value'];
            $friends->where(function ($q) use ($search_text) {
                $q->where('name', 'like', '%'.$search_text.'%');
                $q->orWhere('email', 'like', '%'.$search_text.'%');
            });
        }

        return make_pagination($friends, $pageNumber, $pageSize);
        // return $friends->get();
    }

    public function closestFriend(UserModel $user1, UserModel $user2 = null)
    {
        $friends = $user1->friends()
                         ->whereHas('friends',
                            function ($q) use ($user2) {
                                $q->whereId($user2->id);
                            }
                         )->count();

        return $friends > 0;
    }
}
