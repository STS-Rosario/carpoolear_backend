<?php

namespace STS\Contracts\Repository;

use STS\User as UserModel;
use STS\Entities\Subscription as SubscriptionModel;

interface Subscription
{ 
    public function create(SubscriptionModel $model);

    public function update(SubscriptionModel $model);

    public function show($id);

    public function delete(SubscriptionModel $model);

    public function list(UserModel $user, $active = null);
}
