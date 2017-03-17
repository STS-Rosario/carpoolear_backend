<?php

namespace STS\Services\Logic;

use STS\User as UserModel;
use STS\Entities\Passenger;
use STS\Entities\Trip as TripModel;
use STS\Repository\PassengerRepository;

class PassengersManager
{
    protected $passengerRepo;

    public function __construct()
    {
        $this->passengerRepo = new PassengerRepository();
    }

    public function add($user, $trip)
    {
        $p = new Passenger();
        $p->user_id = $user->id;
        $p->passenger_type = Passenger::TYPE_PASAJERO;
        $p->request_state = Passenger::STATE_PENDIENTE;

        return $trip->passenger()->save($p);
    }

    public function remove($user, $trip)
    {
        return $trip->passenger()->whereUserId($user->id)->delete();
    }

    public function find($user, $trip)
    {
        return $trip->passenger()->whereUserId($user->id)->first();
    }

    public function rideUp($user, $trip)
    {
        if ($trip->disponibles() > 0) {
            $p = $this->find($user, $trip);
            if ($p && $p->request_state == Passenger::STATE_RECHAZADO) {
                $p->request_state = Passenger::STATE_PENDIENTE;

                return $p->save();
            } elseif (is_null($p)) {
                return $this->add($user, $trip);
            }
        }
    }

    public function rideDown($user, $trip)
    {
        $p = $this->find($user, $trip);
        if ($p && $p->request_state == Passenger::STATE_ACEPTADO) {
            $p->delete();

            return true;
        }
    }

    public function confirm($user, $trip, $who)
    {
        if ($trip->user->id == $user->id) {
            $p = $this->find($who, $trip);
            if ($p && $p->request_state == Passenger::STATE_PENDIENTE) {
                if ($trip->disponibles() > 0) {
                    $p->request_state = Passenger::STATE_ACEPTADO;

                    return $p->save();
                } else {
                    return 'No hay más espacio disponible';
                }
            }
        }
    }

    public function reject($user, $trip, $who)
    {
        if ($trip->user->id == $user->id) {
            $p = $this->find($user, $trip);
            if ($p && $p->request_state == Passenger::STATE_PENDIENTE) {
                $p->request_state = Passenger::STATE_RECHAZADO;

                return $p->save();
            }
        }
    }

    public function userIsOnTrip(TripModel $trip, UserModel $user)
    {
        return true; //stub function
    }
}
