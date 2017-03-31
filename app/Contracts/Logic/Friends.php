<?php

namespace STS\Contracts\Logic;

use STS\User as UserModel;

interface Friends
{
    public function areFriend(UserModel $Who, UserModel $user, $friendOfFriends = false);

    public function request(UserModel $Who, UserModel $user);

    public function accept(UserModel $Who, UserModel $user);

    public function delete(UserModel $Who, UserModel $user);

    public function reject(UserModel $Who, UserModel $user);

    public function make(UserModel $Who, UserModel $user);

    public function getFriends(UserModel $Who);

    public function getPendings(UserModel $Who);

    public function setErrors($errs);

    public function getErrors();
}
