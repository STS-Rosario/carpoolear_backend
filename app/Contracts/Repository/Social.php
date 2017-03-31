<?php

namespace STS\Contracts\Repository;

use STS\User as UserModel;
use STS\Entities\SocialAccount;

interface Social
{
    public function setDefaultProvider($provider);

    public function find($provider_user_id, $provider = null);

    public function create(UserModel $user, $provider_user_id, $provider = null);

    public function delete(SocialAccount $account);

    public function get(UserModel $user, $provider = null);
}
