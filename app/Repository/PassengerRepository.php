<?php

namespace STS\Repository;

use STS\Entities\Passenger;

class PassengerRepository
{
    public function add($trip, $user)
    {
        $p = new Passenger();
        $p->user_id = $user->id;
        $p->passenger_type = Passenger::TYPE_PASAJERO;
        $p->request_state = Passenger::STATE_PENDING;

        return $trip->passenger()->save($p);
    }

    public function delete($trip, $user)
    {
        return $trip->passenger()->whereUserId($user->id)->delete();
    }

    public function find($trip, $user)
    {
        return $trip->passenger()->whereUserId($user->id)->first();
    }

    public function accept($passenger)
    {
        $passenger->request_state = Passenger::STATE_ACCEPTED;

        return $p->save();
    }

    public function reject($passenger)
    {
        $passenger->request_state = Passenger::STATE_REJECTED;

        return $p->save();
    }

    public function cancel($passenger)
    {
        $passenger->request_state = Passenger::STATE_CANCELED;

        return $p->save();
    }

    public function count($trip)
    {
        return $trip->passenger_count;
    }

    public function available($trip)
    {
        return $trip->seats_available;
    }

    public function penddingRequest($trip)
    {
        return $trip->passengerPending()->get();
    }
}
