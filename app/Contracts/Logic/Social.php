<?php

namespace STS\Contracts\Logic;

use STS\User as UserModel;

interface Social
{
    public function loginOrCreate($data);

    public function makeFriends(UserModel $user);

    public function updateProfile(UserModel $user);

    public function linkAccount(UserModel $user);

    public function setErrors($errs);

    public function getErrors();
}
