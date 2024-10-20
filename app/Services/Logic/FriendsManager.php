<?php

namespace STS\Services\Logic;

use STS\Repository\FriendsRepository;
use STS\Models\User as UserModel;
use STS\Events\Friend\Cancel as CancelEvent;
use STS\Events\Friend\Accept  as AcceptEvent;
use STS\Events\Friend\Reject  as RejectEvent;
use STS\Events\Friend\Request as RequestEvent; 

class FriendsManager extends BaseManager
{
    protected $friendsRepo;

    public function __construct(FriendsRepository $friends)
    {
        $this->friendsRepo = $friends;
    }

    public function areFriend(UserModel $who, UserModel $user, $friendOfFriends = false)
    {
        $areFriend = $this->friendsRepo->get($who, $user, UserModel::FRIEND_ACCEPTED)->count() > 0;
        if ($friendOfFriends) {
            $areFriend = $areFriend || $this->friendsRepo->closestFriend($who, $user);
        }

        return $areFriend;
    }

    public function request(UserModel $who, UserModel $user)
    {
        if ($this->friendsRepo->get($who, $user, UserModel::FRIEND_ACCEPTED)->count() == 0) {
            $this->friendsRepo->delete($who, $user);
            $this->friendsRepo->delete($user, $who);

            $this->friendsRepo->add($who, $user, UserModel::FRIEND_REQUEST);

            event(new RequestEvent($who, $user));

            return true;
        } else {
            $this->setErrors(['error' => 'Operación inválida']);

            return;
        }
    }

    public function accept(UserModel $who, UserModel $user)
    {
        if ($this->friendsRepo->get($user, $who, UserModel::FRIEND_REQUEST)->count() > 0) {
            $this->friendsRepo->delete($who, $user);
            $this->friendsRepo->delete($user, $who);
            $this->friendsRepo->add($who, $user, UserModel::FRIEND_ACCEPTED);
            $this->friendsRepo->add($user, $who, UserModel::FRIEND_ACCEPTED);
            event(new AcceptEvent($who, $user));
            // tengo que 
            return true;
        } else {
            $this->setErrors(['error' => 'Operación inválida']);

            return;
        }
    }

    public function reject(UserModel $who, UserModel $user)
    {
        if ($this->friendsRepo->get($user, $who, UserModel::FRIEND_REQUEST)->count() > 0) {
            $this->friendsRepo->delete($user, $who);
            //$this->friendsRepo->add($who, $user, UserModel::FRIEND_REJECT );
            event(new RejectEvent($who, $user));

            return true;
        } else {
            $this->setErrors(['error' => 'Operación inválida']);

            return;
        }
    }

    public function delete(UserModel $who, UserModel $user)
    {
        if ($this->friendsRepo->get($who, $user, UserModel::FRIEND_ACCEPTED)->count() > 0) {
            $this->friendsRepo->delete($who, $user);
            $this->friendsRepo->delete($user, $who);
            event(new CancelEvent($who, $user));

            return true;
        } else {
            $this->setErrors(['error' => 'Operación inválida']);

            return;
        }
    }

    public function make(UserModel $who, UserModel $user)
    {
        $this->friendsRepo->delete($who, $user);
        $this->friendsRepo->delete($user, $who);
        $this->friendsRepo->add($user, $who, UserModel::FRIEND_ACCEPTED);
        $this->friendsRepo->add($who, $user, UserModel::FRIEND_ACCEPTED);

        return true;
    }

    public function getFriends(UserModel $who, $data = [])
    {
        return $this->friendsRepo->get($who, null, UserModel::FRIEND_ACCEPTED, $data);
    }

    public function getPendings(UserModel $who)
    {
        return $this->friendsRepo->getPending($who);
    }
}
