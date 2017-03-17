<?php

namespace STS\Services\Logic;

use STS\Contracts\Logic\Friends as FriendsLogic;
use STS\Contracts\Repository\Friends as FriendsRepo;
use STS\Events\Friend\Accept  as AcceptEvent;
use STS\Events\Friend\Reject  as RejectEvent;
use STS\Events\Friend\Request as RequestEvent;
use STS\User as UserModel;

class FriendsManager extends BaseManager implements FriendsLogic
{
    protected $friendsRepo;

    public function __construct(FriendsRepo $friends)
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
            //$this->friendsRepo->add($user, $who, UserModel::FRIEND_REQUEST );

            event(new RequestEvent($who->id, $user->id));

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
            event(new AcceptEvent($who->id, $user->id));

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
            event(new RejectEvent($who->id, $user->id));

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

    public function getFriends(UserModel $who)
    {
        return $this->friendsRepo->get($who, null, UserModel::FRIEND_ACCEPTED);
    }

    public function getPendings(UserModel $who)
    {
        return $this->friendsRepo->get($who, null, UserModel::FRIEND_REQUEST);
    }
}
