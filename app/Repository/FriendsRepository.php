<?php

namespace STS\Repository;

use STS\Models\User as UserModel;
use STS\Models\Trip as TripModel;
use STS\Models\TripVisibility as TripVisibilityModel;

use DB;


class FriendsRepository
{
    public function add(UserModel $user1, UserModel $user2, $state)
    {
        $user1->allFriends()->attach($user2->id, ['state' => $state]);
        if ($state == UserModel::FRIEND_ACCEPTED) {
            $this->generateFriendTripVisibility($user1, $user2);
        }
    }

    public function delete(UserModel $user1, UserModel $user2)
    {
        $user1->allFriends()->detach($user2->id);
        $this->undoFriendTripVisibility($user1, $user2);
    }

    public function get(UserModel $user1, UserModel $user2 = null, $state = null, $data = [])
    {
        $pageNumber = isset($data['page']) ? $data['page'] : null;
        $pageSize = isset($data['page_size']) ? $data['page_size'] : null;

        $friends = $user1->allFriends($state);
        if ($user2) {
            $friends->where('id', $user2->id);
        }

        if (isset($data['value'])) {
            $search_text = $data['value'];
            $friends->where(function ($q) use ($search_text) {
                $q->where('name', 'like', '%'.$search_text.'%');
                // $q->orWhere('email', 'like', '%'.$search_text.'%');
            });
        }

        return make_pagination($friends, $pageNumber, $pageSize);
        // return $friends->get();
    }

    public function getPending(UserModel $user)
    {
        $users = UserModel::whereHas('allFriends', function ($q) use ($user) {
            $q->where('id', $user->id);
            $q->where('state', UserModel::FRIEND_REQUEST);
        });

        return $users->get();
    }

    public function closestFriend(UserModel $user1, UserModel $user2 = null)
    {
        $friends = $user1->friends()
                         ->whereHas('friends',
                            function ($q) use ($user2) {
                                $q->whereId($user2->id);
                            }
                         )->count();

        return $friends > 0;
    }

    public function generateFriendTripVisibility ($requestedUser, $userAccepted) {
        // somos amigos
        // ahora deberia ver todos los viajes que publicaste como only friends
        // tambien deberia ver los viajes de amigos de amigos publicados como FoF
        $ownTrips = $userAccepted->trips(TripModel::ACTIVO)->where('friendship_type_id', '<', TripModel::PRIVACY_PUBLIC);
        // \Log::info($userAccepted->name . ': ' . $ownTrips->count());
        $data = [];

        $ownTrips->each(function ($t) use (&$data, $requestedUser) {
            $data[] = [
                'user_id' => $requestedUser->id,
                'trip_id' => $t->id
            ];
        });


        $friendsTrips = $userAccepted->friends()->each(function ($f) use (&$data, $requestedUser) {
            
            $friendTrips = $f->trips(TripModel::ACTIVO)->where('friendship_type_id', TripModel::PRIVACY_FOF);
            // \Log::info($f->name . ': ' . $friendTrips->count());
            $friendTrips->each(function ($t) use (&$data, $requestedUser) {
                $data[] = [
                    'user_id' => $requestedUser->id,
                    'trip_id' => $t->id
                ];
            });
        });
        // \Log::info($data);
        if (count($data)) {
            TripVisibilityModel::insert($data);
        }
    }

    public function undoFriendTripVisibility ($requestedUser, $userRejected) {
        $ownTrips = $userRejected->trips(TripModel::ACTIVO)->where('friendship_type_id', '<', TripModel::PRIVACY_PUBLIC);

        $data = [];
        // \Log::info($userRejected->name . ': ' . $ownTrips->count());

        $ownTrips->each(function ($t) use (&$data, $requestedUser) {
            $data[] = [ $requestedUser->id, $t->id ];
        });

        $userRejected->friends()->each(function ($f) use (&$data, $requestedUser) {
            $friendTrips = $f->trips(TripModel::ACTIVO)->where('friendship_type_id', TripModel::PRIVACY_FOF);
            // \Log::info($f->name . ': ' . $friendTrips->count());
            $friendTrips->each(function ($t) use (&$data, $requestedUser) {
                $data[] = [ $requestedUser->id, $t->id ];
            });
        });

        // TripVisibilityModel::delete($data);
        // \Log::info($data);
        foreach ($data as $row) {
            DB::delete('DELETE FROM user_visibility_trip WHERE user_id = ? AND trip_id = ?', $row);
        }


    }
}
