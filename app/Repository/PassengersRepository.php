<?php

namespace STS\Repository;

use Carbon\Carbon;
use STS\Entities\Passenger;
use STS\Contracts\Repository\IPassengersRepository;
use STS\Entities\Trip;

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
            /* $passengers = Passenger::whereHas('trip', function ($q) use ($user) {
                $q->where('user_id', $user->id);
                $q->where('trip_date', '>=', Carbon::Now()->toDateTimeString());
            }); */
            $passengers = Passenger::query();
            $passengers->join('trips', 'trips.id', '=', 'trip_passengers.trip_id');
            $passengers->whereNull('trips.deleted_at');
            $passengers->where('trips.user_id', $user->id);
            $passengers->where('trips.trip_date', '>=', Carbon::Now()->toDateTimeString());
        }
        $passengers->with('user');
        $passengers->where('request_state', Passenger::STATE_PENDING);
        $passengers->select('trip_passengers.*');

        $pageNumber = isset($data['page']) ? $data['page'] : null;
        $pageSize = isset($data['page_size']) ? $data['page_size'] : null;

        return $passengers->get(); // make_pagination($passengers, $pageNumber, $pageSize);
    }


    public function getPendingPaymentRequests($tripId, $user, $data)
    {
        $passengers = Passenger::query();
        $passengers->join('trips', 'trips.id', '=', 'trip_passengers.trip_id');
        $passengers->whereNull('trips.deleted_at');
        $passengers->where('trip_passengers.user_id', $user->id);
        $passengers->where('trips.trip_date', '>=', Carbon::Now()->toDateTimeString());
        $passengers->with('user');
        $passengers->where('request_state', Passenger::STATE_WAITING_PAYMENT);
        $passengers->select('trip_passengers.*');

        $pageNumber = isset($data['page']) ? $data['page'] : null;
        $pageSize = isset($data['page_size']) ? $data['page_size'] : null;

        return $passengers->get(); // make_pagination($passengers, $pageNumber, $pageSize);
    }

    public function newRequest($tripId, $user, $data = [])
    {
        $newRequestData = [
            'trip_id' => $tripId,
            'user_id' => $user->id,
            'request_state' => Passenger::STATE_PENDING,
            'passenger_type' => Passenger::TYPE_PASAJERO,
        ];

        $newRequest = Passenger::create($newRequestData);

        return $newRequest;
    }

    private function changeRequestState($tripId, $userId, $newState, $criterias, $canceledState = null)
    {
        $updateData = [
            'request_state' => $newState,
            'canceled_state' => $canceledState,
        ];

        $request = Passenger::where('trip_id', $tripId);

        $request->where('user_id', $userId);

        if (! empty($criterias)) {
            foreach ($criterias as $column => $value) {
                $request->where($column, $value);
            }
        }

        $request->where('passenger_type', Passenger::TYPE_PASAJERO);

        $passenger = $request->first();
        if ($passenger) {
            // $request->update($updateData);
            foreach ($updateData as $key => $value) {
                $passenger->{$key} = $value;
            }
            $passenger->save();
            return $passenger;
        } else {
            return null;
        }
    }

    public function cancelRequest($tripId, $user, $canceledState)
    {
        if ($canceledState == Passenger::CANCELED_REQUEST) {
            $criteria = [
                'request_state' => Passenger::STATE_PENDING,
            ];
        } else {
            if ($canceledState == Passenger::CANCELED_PASSENGER_WHILE_PAYING) {
                $criteria = [
                    'request_state' => Passenger::STATE_WAITING_PAYMENT,
                ];
            } else {
                $criteria = [
                    'request_state' => Passenger::STATE_ACCEPTED,
                ];
            }
        }

        $cancelRequest = $this->changeRequestState($tripId, $user->id, Passenger::STATE_CANCELED, $criteria, $canceledState);

        return $cancelRequest;
    }

    public function aproveForPaymentRequest($tripId, $aprovalUserId, $user, $data)
    {
        $criteria = [
            'request_state' => Passenger::STATE_PENDING,
        ];

        $request = $this->changeRequestState($tripId, $aprovalUserId, Passenger::STATE_WAITING_PAYMENT, $criteria);

        return $request;
    }

    public function payRequest($tripId, $aprovalUserId, $user, $data)
    {
        $criteria = [
            'request_state' => Passenger::STATE_WAITING_PAYMENT,
        ];

        $request = $this->changeRequestState($tripId, $aprovalUserId, Passenger::STATE_ACCEPTED, $criteria);

        return $request;
    }

    public function acceptRequest($tripId, $acceptedUserId, $user, $data)
    {
        $criteria = [
            'request_state' => Passenger::STATE_PENDING,
        ];

        $acceptRequest = $this->changeRequestState($tripId, $acceptedUserId, Passenger::STATE_ACCEPTED, $criteria);

        return $acceptRequest;
    }

    public function rejectRequest($tripId, $rejectedUserId, $user, $data)
    {
        $criteria = [
            'request_state' => Passenger::STATE_PENDING,
        ];

        $rejectedRequest = $this->changeRequestState($tripId, $rejectedUserId, Passenger::STATE_REJECTED, $criteria);

        return $rejectedRequest;
    }


    public function tripsWithTransactions ($user) {
        /* $query = Trip::where(function ($q) use ($user) {
            $q->where('user_id', $user->id);
            $q->orWhereHas('passenger', function ($q) use ($user) {
                $q->where('user_id', $user->id);
            });
        }); */

        $query = Trip::query();
        $query->join('trip_passengers', 'trips.id', '=', 'trip_passengers.trip_id');
        $query->where(function ($q) use ($user) {
            $q->where('trips.user_id', $user->id);
            $q->orWhere('trip_passengers.user_id', $user->id);
        });
        $query->whereNotNull('trip_passengers.payment_status');
        $query->whereNull('trips.deleted_at');
        $query->where('trips.trip_date', '<=', Carbon::Now()->toDateTimeString());

        $query->select('trips.*')->distinct();

        $query->with([
            'user',
            'passenger.trip.user'
        ]);
        // $r = $query->toSql();
        // var_dump($r);die;
        return $query->get();
    }
    
    public function userHasActiveRequest($tripId, $userId)
    {
        $query = Passenger::where('trip_id', $tripId);

        $query->where('user_id', $userId);

        $query->whereIn('request_state', [
            Passenger::STATE_WAITING_PAYMENT,
            Passenger::STATE_ACCEPTED,
            Passenger::STATE_PENDING
        ]);

        return $query->get()->count() > 0;
    }

    private function isUserInRequestType($tripId, $userId, $requestType)
    {
        $query = Passenger::where('trip_id', $tripId);

        $query->where('user_id', $userId);

        $query->where('request_state', $requestType);

        return $query->get()->count() > 0;
    }

    public function isUserRequestWaitingPayment($tripId, $userId)
    {
        return $this->isUserInRequestType($tripId, $userId, Passenger::STATE_WAITING_PAYMENT);
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
