<?php

namespace STS\Repository;

use DB;
use STS\User;
use Carbon\Carbon;
use STS\Entities\Passenger;
use STS\Contracts\Repository\IPassengersRepository;

class PassengersRepository implements IPassengersRepository
{
    public function getPassengers($tripId, $user, $data)
    {
        $passengers = Passenger::where('trip_id', $tripId);

        $passengers->whereIn('request_state', [Passenger::STATE_ACCEPTED]); //TODO: ver si hace falta obtener pasajeros con otro request_state

        $pageNumber = isset($data['page']) ? $data['page'] : null;
        $pageSize = isset($data['page_size']) ? $data['page_size'] : null;

        return make_pagination($passengers, $pageNumber, $pageSize);
    }

    public function getPendingRequests($tripId, $user, $data)
    {
        if ($tripId) {
            $passengers = Passenger::where('trip_id', $tripId);
        } else {
            $passengers = Passenger::whereHas('trip', function ($q) use ($user){
                $q->where('user_id', $user->id);
            });       
        }
        $passengers->whereIn('request_state', [Passenger::STATE_PENDING]);

        $pageNumber = isset($data['page']) ? $data['page'] : null;
        $pageSize = isset($data['page_size']) ? $data['page_size'] : null;

        return make_pagination($passengers, $pageNumber, $pageSize);
    }

    public function newRequest($tripId, $user, $data = [])
    {
        $newRequestData = [
            'trip_id' => $tripId,
            'user_id' => $user->id,
            'request_state' => Passenger::STATE_PENDING,
            'passenger_type' => Passenger::TYPE_PASAJERO
        ];

        $newRequest = Passenger::create($newRequestData);

        return $newRequest;
    }

    private function changeRequestState($tripId, $userId, $newState, $criterias)
    {
        $updateData = [
            'request_state' => $newState
        ];

        $request = Passenger::where('trip_id', $tripId);

        $request->where('user_id', $userId);

        if (!empty($criterias)) {
            foreach ($criterias as $column => $value) {
                $request->where($column, $value);
            }
        }

        $request->where('passenger_type', Passenger::TYPE_PASAJERO);

        $request->update($updateData);

        return $request;
    }

    public function cancelRequest($tripId, $user, $data)
    {
        $criteria = [
            'request_state' => Passenger::STATE_PENDING
        ];

        $cancelRequest = $this->changeRequestState($tripId, $user->id, Passenger::STATE_CANCELED, $criteria);

        return $cancelRequest;
    }

    public function acceptRequest($tripId, $acceptedUserId, $user, $data)
    {
        $acceptRequest = $this->changeRequestState($tripId, $acceptedUserId, Passenger::STATE_ACCEPTED, null);

        return $acceptRequest;
    }

    public function rejectRequest($tripId, $rejectedUserId, $user, $data)
    {
        $rejectedRequest = $this->changeRequestState($tripId, $rejectedUserId, Passenger::STATE_REJECTED, null);

        return $rejectedRequest;
    }

    private function isUserInRequestType($tripId, $userId, $requestType)
    {
        $query = Passenger::where('trip_id', $tripId);

        $query->where('user_id', $userId);

        $query->where('request_state', $requestType);

        return $query->get()->count() > 0;
    }

    public function isUserRequestAccepted($tripId, $userId)
    {
        return $this->isUserInRequestType($tripId, $userId, Passenger::STATE_ACCEPTED);
    }
    
    public function isUserRequestRejected($tripId, $userId)
    {
        return $this->isUserInRequestType($tripId, $userId, Passenger::STATE_REJECTED);
    }
    
    public function isUserRequestPending($tripId, $userId)
    {
        return $this->isUserInRequestType($tripId, $userId, Passenger::STATE_PENDING);
    }
}
