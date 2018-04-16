<?php

namespace STS\Repository;

use STS\User as UserModel;
use STS\Entities\Subscription as SubscriptionModel;
use STS\Contracts\Repository\Subscription as SubscriptionRepository;

class SubscriptionsRepository implements SubscriptionRepository
{
    public function create(SubscriptionModel $model)
    {
        return $model->save();
    }

    public function update(SubscriptionModel $model)
    {
        return $model->save();
    }

    public function show($id)
    {
        return SubscriptionModel::find($id);
    }

    public function delete(SubscriptionModel $model)
    {
        return $model->delete();
    }

    public function list(UserModel $user, $active = null) {
        if ($active == null) {
            return $user->subscriptions;
        } else {
            return $user->subscriptions()->where('state', $active)->get();
        }
        
    }

}
