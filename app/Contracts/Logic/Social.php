<?php

namespace STS\Contracts\Logic;

use STS\Models\User;

interface Social
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function loginOrCreate($data);

    public function updateProfile(User $user);

    public function makeFriends(User $user);

    public function getErrors();
}
