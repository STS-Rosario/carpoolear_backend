<?php

namespace STS\Contracts\Logic; 

use STS\Contracts\Repository\Friends as FriendsRepo;
use STS\Contracts\Logic\Friends as FriendsLogic;
use STS\User as UserModel;

class FriendsManager implements FriendsLogic
{
    protected $friendsRepo;

    public function __construct (FriendsRepo $friends) {
        $this->friendsRepo = $friends;
    }
    
    public function request(UserModel $Who, UserModel $user) {
        if ($this->friendsRepo->get($who, $user, UserModel::FRIEND_ACCEPTED)->count() == 0) {
            $this->friendsRepo->delete($who, $user);
            $this->friendsRepo->delete($user, $who);

            $this->friendsRepo->add($who, $user, UserModel::FRIEND_REQUEST );
            $this->friendsRepo->add($user, $who, UserModel::FRIEND_REQUEST );

            return true;
        } else {
            return null;
        }
    }

    public function accept(UserModel $Who, UserModel $user) {
        if ($this->friendsRepo->get($who, $user, UserModel::FRIEND_REQUEST)->count() > 0) {
            $this->friendsRepo->delete($who, $user);
            $this->friendsRepo->delete($user, $who);

            $this->friendsRepo->add($who, $user, UserModel::FRIEND_ACCEPTED );
            $this->friendsRepo->add($user, $who, UserModel::FRIEND_ACCEPTED );

            return true;
        } else {
            return null;
        }
    }

    public function reject(UserModel $Who, UserModel $user) {
        if ($this->friendsRepo->get($user, $who, UserModel::FRIEND_REQUEST)->count() > 0) {
            $this->friendsRepo->delete($user, $who);
            $this->friendsRepo->add($who, $user, UserModel::FRIEND_REJECT ); 
            return true;
        } else {
            return null;
        }
    }

    public function delete(UserModel $Who, UserModel $user) {
        if ($this->friendsRepo->get($who, $user, UserModel::FRIEND_ACCEPTED)->count() == 0) {
            $this->friendsRepo->delete($who, $user);
            $this->friendsRepo->delete($user, $who); 
            return true;
        } else {
            return null;
        }
    }

    public function make(UserModel $Who, UserModel $user) {
        $this->friendsRepo->delete($who, $user);
        $this->friendsRepo->delete($user, $who); 
        $this->friendsRepo->add($user, $who, UserModel::FRIEND_ACCEPTED ); 
        $this->friendsRepo->add($who, $user, UserModel::FRIEND_ACCEPTED ); 
    }

    public function getFriends(UserModel $Who) {
        return $this->friendsRepo->get($who, null, UserModel::FRIEND_ACCEPTED);
    }

    public function getPendings(UserModel $Who) {
        return $this->friendsRepo->get($who, null, UserModel::FRIEND_REQUEST);
    }

}