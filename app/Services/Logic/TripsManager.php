<?php

namespace STS\Services\Logic; 

use STS\Repository\TripRepository;
use STS\Entities\Trip;
use STS\Entities\TripPoint;
use STS\User;
use Validator;
use Carbon\Carbon;
use DB;

class TripsManager extends BaseManager
{ 

    protected $tripRepo;
    public function __construct()
    { 
        $this->tripRepo = new TripRepository();
    } 
    
    public function validator(array $data, $id = null)
    {
        if (is_null($id)) {
            return Validator::make($data, [
                'is_passenger'           => 'required|in:0,1',
                'from_town'             => 'required|strng|max:255',
                'to_town'               => 'required|strng|max:255',
                'trip_date'             => 'required|datetime',
                'total_seats'           => 'required|integer|max:5|min:1',
                'friendship_type_id'    => 'required|integer|in:0,1,2',
                'estimated_time'        => 'required|time',
                'distance'              => 'required|numeric',
                'co2'                   => 'required|integer', 

                'points.*.address'      => 'required|string', 
                'points.*.json_address' => 'required|array',
                'points.*.lat'          => 'required|numeric',
                'points.*.lng'          => 'required|numeric',
            ]);
        } else {
            return Validator::make($data, [
                'is_passenger'          => 'in:0,1',
                'from_town'             => 'strng|max:255',
                'to_town'               => 'strng|max:255',
                'trip_date'             => 'datetime',
                'total_seats'           => 'integer|max:5|min:1',
                'friendship_type_id'    => 'integer|in:0,1,2',
                'estimated_time'        => 'time',
                'distance'              => 'numeric',
                'co2'                   => 'integer', 

                'points.*.address'      => 'string', 
                'points.*.json_address' => 'array',
                'points.*.lat'          => 'numeric',
                'points.*.lng'          => 'numeric',
            ]);
        }
    } 

    public function create($user, array $data)
    {
        $v = $this->validator($data);
        if ($v->fails()) {
            $this->setErrors($v->errors());
            return null;
        } else {  
            $data["user_id"] = $user->id;
            $trip = $this->tripRepo->create($data);
            return $trip;
        }        
    }

    public function update($user, $trip_id, array $data)
    {
        $trip = $this->tripRepo->show($trip_id);
        if ($trip) {
            if ($user->id == $trip->user->id) {
                $v = $this->validator($data);
                if ($v->fails()) {
                    $this->setErrors($v->errors());
                    return null;
                } else {   
                    $trip = $this->tripRepo->update($trip, $data);
                    return $trip;
                } 
            } else {
                $this->setErrors(trans('errors.tripowner'));
                return null;
            }
        } else {
            $this->setErrors(trans('errors.notrip'));
            return null;
        }
    }


    public function delete($user, $trip_id)
    {
        $trip = $this->tripRepo->show($trip_id);
        if ($trip) {
            // [TODO] Agregar lógica de pasajeros
            if ($user->id == $trip->user->id) {
                $this->tripRepo->delete($trip);
            } else {
                $this->setErrors(trans('errors.tripowner'));
                return null;
            }
        } else {
            $this->setErrors(trans('errors.notrip'));
            return null;
        }
    }
 
    public function show($user, $trip) {

    }

    public function index($user, $data)
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