<?php

namespace STS\Services\Logic; 

use STS\Entities\Trip;
use STS\Entities\TripPoint;
use STS\User;
use Validator;
use Carbon\Carbon;
use DB;

class TripsManager
{ 
    
    public function validator(array $data)
    {
        return Validator::make($data, [
            'passenger_types' => 'required|in:0,1,2',
            'from_town' => 'required|strng|max:255',
            'to_town' => 'required|strng|max:255',
            'trip_date' => 'required|datetime',
            'total_seats' => 'required|integer|max:5|min:1',
            'friendship_type_id' => 'required|integer|in:0,1,2',
            'estimated_time' => 'required|time',
            'distance' => 'required|numeric',
            'co2' => 'required|integer', 

            'points.*.address' => 'required|string', 
            'points.*.json_address' => 'required|array',
            'points.*.lat' => 'required|numeric',
            'points.*.lng' => 'required|numeric',
        ]);
    } 

    public function create($user, array $data)
    {
        $trip = new Trip();
        $trip->es_pasajero          = $data["passenger_types"];

        $trip->from_town            = $data["from_town"];
        $trip->to_town              = $data["to_town"];
        $trip->trip_date            = $data["trip_date"];

        $trip->total_seats          = $data["total_seats"];
        $trip->friendship_type_id   = $data["friendship_type_id"];
        $trip->estimated_time       = $data["estimated_time"];

        $trip->distance             = $data["total_seats"];
        $trip->co2                  = $data["co2"];

        $trip->description          = htmlentities($data["description"]);
        $trip->is_active            = true;

        $trip->mail_send            = false;

        if ($trip->passenger_types == 2) {
            $trip->esRecurrente = 1;
            $trip->trip_date    = null;
        }

        return $user->trips()->save($trip);

        // [FALTA] Lo de los viajes recurrente
        
    }

    public function update($user, $trip, array $data)
    {
        // [FALTA] Lo de los viajes recurrente
        $trip->update($data);
        $trip->points()->delete();

        return $trip;
    }


    public function delete($user, $trip)
    {
        $trip->delete();
        // [FALTA] ver que hacer
    }

    public function updatePoints($trip, $data) {
        foreach ($data as $p) {
            $point = new TripPoint;
            $point->address = $p["address"];
            $point->json_address = $p["json_address"];
            $point->lat = $p["lat"];
            $point->lng = $p["lng"];

            $trip->points()->save($point);
        }
    }

    public function seatUp($trip)
    {
        $trip->total_seats += 1;
        $trip->save();
    }

    public function seatDown($trip)
    {
        $trip->total_seats -= 1;
        $trip->save();
    }

    public function index($user,$data)
    {  
        if (isset($data["date"])){
            $trips = Trip::where($data["date"], DB::Raw("DATE(trip_date)"));
        } else {
            $trips = Trip::where("date", ">=", Carbon::Now());
        }
        
        $trips->where(function ($q) use ($user) {
            $q->whereUserId($user->id);
            $q->orWhere(function ($q) use ($user) {
                $q->whereFriendshipTypeId(Trip::PRIVACY_PUBLIC);
                $q->orWhere(function ($q) use ($user) {
                    $q->whereFriendshipTypeId(Trip::PRIVACY_FRIENDS);
                    $q->whereHas("user.friends",function ($q) use ($user) {
                        $q->whereId($user->id);
                    });
                });
                $q->orWhere(function ($q) use ($user) {
                    $q->whereFriendshipTypeId(Trip::PRIVACY_FOFF);
                    $q->where(function ($q) use ($user) {
                        $q->whereHas("user.friends",function ($q) use ($user) {
                            $q->whereId($user->id);
                        });
                        $q->orWhereHas("user.friends.friends",function ($q) use ($user) {
                            $q->whereId($user->id);
                        });
                    });
                });
            });
        });

        if (isset($data["date"])) {
            //$trips->where();
        }

        $trips->with("user");
        $trips->orderBy("trip_date"); 
        return $trips->get();
        // [FALTA] Tema de la localizacion para viajes publicos
    }
 

}