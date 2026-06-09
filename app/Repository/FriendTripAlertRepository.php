<?php

namespace STS\Repository;

use STS\Models\FriendTripAlertSubscription;
use STS\Models\User as UserModel;

class FriendTripAlertRepository
{
    public function subscribe(UserModel $user, UserModel $friend): void
    {
        FriendTripAlertSubscription::firstOrCreate([
            'user_id' => $user->id,
            'friend_id' => $friend->id,
        ]);
    }

    public function unsubscribe(UserModel $user, UserModel $friend): void
    {
        FriendTripAlertSubscription::where('user_id', $user->id)
            ->where('friend_id', $friend->id)
            ->delete();
    }

    public function toggle(UserModel $user, UserModel $friend): bool
    {
        if ($this->isSubscribed($user, $friend)) {
            $this->unsubscribe($user, $friend);

            return false;
        }

        $this->subscribe($user, $friend);

        return true;
    }

    public function isSubscribed(UserModel $user, UserModel $friend): bool
    {
        return FriendTripAlertSubscription::where('user_id', $user->id)
            ->where('friend_id', $friend->id)
            ->exists();
    }

    public function getSubscribersForDriver(UserModel $driver)
    {
        return UserModel::whereIn('id', function ($query) use ($driver) {
            $query->select('user_id')
                ->from('friend_trip_alert_subscriptions')
                ->where('friend_id', $driver->id);
        })->get();
    }

    public function deleteForUsers(UserModel $user1, UserModel $user2): void
    {
        FriendTripAlertSubscription::where(function ($query) use ($user1, $user2) {
            $query->where('user_id', $user1->id)->where('friend_id', $user2->id);
        })->orWhere(function ($query) use ($user1, $user2) {
            $query->where('user_id', $user2->id)->where('friend_id', $user1->id);
        })->delete();
    }
}
