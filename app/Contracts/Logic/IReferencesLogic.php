<?php

namespace STS\Contracts\Logic;

use STS\User as UserModel;

interface IReferencesLogic
{
    public function create(UserModel $user, $data);
}
