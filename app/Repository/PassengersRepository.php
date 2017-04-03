<?php

namespace STS\Repository;

use DB;
use STS\User;
use Carbon\Carbon;
use STS\Entities\Passenger;
use STS\Contracts\Repository\IPassengersRepository;

class PassengersRepository implements IPassengesRepository
{
    public function getPassengers($tripId, $user, $data)
    {
        $passengers = Passenger::where('trip_id', $tripId);

        $passengers->whereIn('request_state', [Passanger::STATE_ACCEPTED]); //TODO: ver si hace falta obtener pasajeros con otro request_state

        $pageNumber = isset($data['page']) ? $data['page'] : null;
        $pageSize = isset($data['page_size']) ? $data['page_size'] : null;

        return make_pagination($passengers, $pageNumber, $pageSize);
    }

    public function getPendingRequests($tripId, $user, $data)
    {
        $passengers = Passenger::where('trip_id', $tripId);

        $passengers->whereIn('request_state', [Passanger::STATE_PENDING]);

        $pageNumber = isset($data['page']) ? $data['page'] : null;
        $pageSize = isset($data['page_size']) ? $data['page_size'] : null;

        return make_pagination($passengers, $pageNumber, $pageSize);
    }

    public function newRequest($tripId, $user, $data)
    {
        $newRequestData = [
            'tripId' => $tripId,
            'userId' => $user->id,
            'request_type' => Passanger::STATE_PENDING,
            'passenger_type' => Passanger::TYPE_PASAJERO
        ];

        $newRequest = Passenger::create($newRequestData);

        return $newRequest;
    }

    private function changeRequestState($tripId, $userId, $newState, $criterias)
    {
        $updateData = [
            'request_type' => $newState
        ];

        $request = Passenger::where('trip_id', $tripId);

        $request->where('user_id', $userId);

        if (!empty($criterias)) {
            foreach ($criterias as $column => $value) {
                $request->where($columnkey, $value);
            }
        }

        $request->where('passenger_type', Passanger::TYPE_PASAJERO);

        $request->update($updateData);

        return $request;
    }

    public function cancelRequest($tripId, $user, $data)
    {
        $criteria = [
            'request_type' => Passanger::STATE_PENDING
        ];

        $cancelRequest = $this->changeRequestState($tripId, $user->id, Passanger::STATE_CANCELED, $criteria);

        return $cancelRequest;
    }

    public function acceptRequest($tripId, $acceptedUserId, $user, $data)
    {
        $acceptRequest = $this->changeRequestState($tripId, $acceptedUserId, Passanger::STATE_ACCEPTED, null);

        return $acceptRequest;
    }

    public function rejectRequest($tripId, $rejectedUserId, $user, $data)
    {
        $rejectedRequest = $this->changeRequestState($tripId, $rejectedUserId, Passanger::STATE_REJECTED, null);

        return $rejectedRequest;
    }

    private function isUserInRequestType($tripId, $userId, $requestType)
    {
        $query = Passanger::where('trip_id', $tripId);

        $query->where('user_id', $userId);

        $query->where('request_type', $requestType);

        return $query->get()-count() > 0;
    }

    public function isUserRequestAccepted($tripId, $userId)
    {
        return $this->isUserInRequestType($tripId, $userId, Passanger::STATE_ACCEPTED);
    }
    
    public function isUserRequestRejected($tripId, $userId)
    {
        return $this->isUserInRequestType($tripId, $userId, Passanger::STATE_REJECTED);
    }
    
    public function isUserRequestPending($tripId, $userId)
    {
        return $this->isUserInRequestType($tripId, $userId, Passanger::STATE_PENDING);
    }
}
