<?php

namespace STS\Services\Logic;

use STS\User;
use Validator;
use STS\Entities\Trip;
use STS\Contracts\Logic\Trip as TripLogic;
use STS\Events\Trip\Create  as CreateEvent;
use STS\Events\Trip\Update  as UpdateEvent;
use STS\Contracts\Repository\Trip as TripRepo;

class TripsManager extends BaseManager implements TripLogic
{
    protected $tripRepo;

    public function __construct(TripRepo $trips)
    {
        $this->tripRepo = $trips;
    }

    public function validator(array $data, $user_id, $id = null)
    {
        if (is_null($id)) {
            return Validator::make($data, [
                'is_passenger'          => 'required|in:0,1',
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
                'car_id'                => 'exists:cars,id,user_id,'.$user_id,

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
                'car_id'                => 'exists:cars,id,user_id,'.$user_id,

                'points.*.address'      => 'string',
                'points.*.json_address' => 'array',
                'points.*.lat'          => 'numeric',
                'points.*.lng'          => 'numeric',
            ]);
        }
    }

    public function create($user, array $data)
    {
        $v = $this->validator($data, $user->id);
        if ($v->fails()) {
            $this->setErrors($v->errors());

            return;
        } else {
            $data['user_id'] = $user->id;
            $trip = $this->tripRepo->create($data);
            event(new CreateEvent($trip));

            return $trip;
        }
    }

    public function update($user, $trip_id, array $data)
    {
        $trip = $this->tripRepo->show($trip_id);
        if ($trip) {
            if ($user->id == $trip->user->id || $user->is_admin) {
                $v = $this->validator($data, $user->id, $trip_id);
                if ($v->fails()) {
                    $this->setErrors($v->errors());

                    return;
                } else {
                    $trip = $this->tripRepo->update($trip, $data);
                    event(new UpdateEvent($trip));

                    return $trip;
                }
            } else {
                $this->setErrors(trans('errors.tripowner'));

                return;
            }
        } else {
            $this->setErrors(trans('errors.notrip'));

            return;
        }
    }

    public static function exist ( $trip_id ) {
        return true;
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

                return;
            }
        } else {
            $this->setErrors(trans('errors.notrip'));

            return;
        }
    }

    public function show($user, $trip_id)
    {
        $trip = $this->tripRepo->show($trip_id);
        if ($this->userCanSeeTrip($user, $trip)) {
            return $trip;
        } else {
            $this->setErrors('trip_not_foound');

            return;
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
