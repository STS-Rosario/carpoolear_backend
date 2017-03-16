<?php

namespace STS\Services\Logic;

use STS\Contracts\Logic\Trip as TripLogic;
use STS\Contracts\Repository\Trip as TripRepo;
use STS\Entities\Trip;
use STS\Entities\TripPoint;
use STS\User;
use Validator;
use Carbon\Carbon;
use DB;

use STS\Events\Trip\Create  as CreateEvent;
use STS\Events\Trip\Update  as UpdateEvent;

class TripsManager extends BaseManager implements TripLogic
{
    protected $tripRepo;
    
    public function __construct(TripRepo $trips)
    {
        $this->tripRepo = $trips;
    }
    
    public function validator(array $data, $id = null)
    {
        if (is_null($id)) {
            return Validator::make($data, [
                'is_passenger'           => 'required|in:0,1',
                'from_town'             => 'required|string|max:255',
                'to_town'               => 'required|string|max:255',
                'trip_date'             => 'required|date',
                'total_seats'           => 'required|integer|max:5|min:1',
                'friendship_type_id'    => 'required|integer|in:0,1,2',
                'estimated_time'        => 'required|string',
                'distance'              => 'required|numeric',
                'co2'                   => 'required|integer',
                'description'           => 'string',
                'return_trip_id'        => 'exists:trips,id',

                'points.*.address'      => 'required|string',
                'points.*.json_address' => 'required|array',
                'points.*.lat'          => 'required|numeric',
                'points.*.lng'          => 'required|numeric',
            ]);
        } else {
            return Validator::make($data, [
                'is_passenger'          => 'in:0,1',
                'from_town'             => 'string|max:255',
                'to_town'               => 'string|max:255',
                'trip_date'             => 'date',
                'total_seats'           => 'integer|max:5|min:1',
                'friendship_type_id'    => 'integer|in:0,1,2',
                'estimated_time'        => 'string',
                'distance'              => 'numeric',
                'co2'                   => 'integer',
                'return_trip_id'        => 'exists:trips,id',
                
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
            event(new CreateEvent($trip, isset($data['enc_path']) ? $data['enc_path'] : null ));
            return $trip;
        }
    }

    public function update($user, $trip_id, array $data)
    {
        $trip = $this->tripRepo->show($trip_id);
        if ($trip) {
            if ($user->id == $trip->user->id || $user->is_admin) {
                $v = $this->validator($data, $trip_id);
                if ($v->fails()) {
                    $this->setErrors($v->errors());
                    return null;
                } else {
                    $trip = $this->tripRepo->update($trip, $data);
                    event(new UpdateEvent($trip, isset($data['enc_path']) ? $data['enc_path'] : null ));
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
            if ($user->id == $trip->user->id || $user->is_admin) {
                return $this->tripRepo->delete($trip);
            } else {
                $this->setErrors(trans('errors.tripowner'));
                return null;
            }
        } else {
            $this->setErrors(trans('errors.notrip'));
            return null;
        }
    }
 
    public function show($user, $trip_id)
    {
        $trip = $this->tripRepo->show($trip_id);
        if ($this->userCanSeeTrip($user, $trip)) {
            return $trip;
        } else {
            $this->setErrors("trip_not_foound");
            return null;
        }
        
    }

    public function index($user, $data)
    {
        return $this->tripRepo->index($user, $data);
    }

    public function userCanSeeTrip($user, $trip) 
    {
        $friendsManager = \App::make('\STS\Contracts\Logic\Friends');
        if ($user->id == $trip->user->id) {
            return true;
        }

        if ($trip->friendship_type_id == Trip::PRIVACY_PUBLIC) {
            return true;
        }

        if ($trip->friendship_type_id == Trip::PRIVACY_FRIENDS) {
            return $friendsManager->areFriend($user, $trip->user);
        }

        if ($trip->friendship_type_id == Trip::PRIVACY_FOF) {
            return $friendsManager->areFriend($user, $trip->user, true);
        }

        // [TODO] Faltaría saber si sos pasajero

        return false;

    }
}
