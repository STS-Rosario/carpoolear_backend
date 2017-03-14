<?php

namespace STS\Repository;

use STS\Entities\Trip;
use STS\Entities\TripPoint;
use STS\User;
use Carbon\Carbon;
use Validator;
use DB;

class TripRepository
{
    public function create(array $data)
    {
        $points = $data['points'];
        unset($data['points']);
        $trip = Trip::create($data);
        $this->addPoints($trip, $points);
        return $trip;
    }

    public function update($trip, array $data)
    {
        $points = null;
        if (isset($points)) {
            $points = $data['points'];
            unset($data['points']);
        }
        $trip = $trip->update($data);
        if ($points) {
            $this->deletePoints($trip);
            $this->addPoints($trip, $points);
        }
        return $trip;
    }

    public function show($id)
    {
        return Trip::with('points')->whereId($id)->first();
    }

    public function index($user, $data)
    {
        if (isset($data['date'])) {
            $trips = Trip::where($data['date'], DB::Raw('DATE(trip_date)'));
        } else {
            $trips = Trip::where('date', '>=', Carbon::Now());
        }
        
        $trips->where(function ($q) use ($user) {
            $q->whereUserId($user->id);
            $q->orWhere(function ($q) use ($user) {
                $q->whereFriendshipTypeId(Trip::PRIVACY_PUBLIC);
                $q->orWhere(function ($q) use ($user) {
                    $q->whereFriendshipTypeId(Trip::PRIVACY_FRIENDS);
                    $q->whereHas('user.friends', function ($q) use ($user) {
                        $q->whereId($user->id);
                    });
                });
                $q->orWhere(function ($q) use ($user) {
                    $q->whereFriendshipTypeId(Trip::PRIVACY_FOFF);
                    $q->where(function ($q) use ($user) {
                        $q->whereHas('user.friends', function ($q) use ($user) {
                            $q->whereId($user->id);
                        });
                        $q->orWhereHas('user.friends.friends', function ($q) use ($user) {
                            $q->whereId($user->id);
                        });
                    });
                });
            });
        });

        $trips->with('user');
        $trips->orderBy('trip_date');
        return $trips->get();
        // [FALTA] Tema de la localizacion para viajes publicos
    }

    public function delete($trip)
    {
        return $trip-delete();
    }
 
    public function addPoints($trip, $points)
    {
        foreach ($points as $point) {
            $p = new TripPoint;
            $p->address = $point["address"];
            $p->json_address = $point["json_address"];
            $p->lat = $point["lat"];
            $p->lng = $point["lng"];
            $trip->points()->save($p);
        }
    }

    public function deletePoints($trip, $points)
    {
        $trip->points()->delete();
    }
}
