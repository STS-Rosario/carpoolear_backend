<?php

namespace STS\Contracts\Repository;

use STS\User as UserModel;

interface Friends
{
    public function add(UserModel $user1, UserModel $user2, $state);

    public function delete(UserModel $user1, UserModel $user2);

    public function get(UserModel $user1, UserModel $user2 = null, $state = null, $data = []);

    public function closestFriend(UserModel $user1, UserModel $user2 = null);
}
