<?php

namespace STS\Services\Logic;

use Validator;

use STS\User;
use STS\Entities\Passanger;
use STS\Contracts\Logic\IPassengersLogic;
use STS\Contracts\Repository\IPassengersRepository;
use STS\Contracts\Logic\Trip as TripLogic;

use STS\Events\Passenger\Accept as AcceptEvent;
use STS\Events\Passenger\Cancel as CancelEvent;
use STS\Events\Passenger\Reject as RejectEvent;
use STS\Events\Passenger\Request as RequestEvent;

class PassengersManager extends BaseManager implements IPassengersLogic
{
    protected $passengerRepository;
    protected $tripLogic;
    
    public function __construct(IPassengersRepository $passengerRepository, TripLogic $tripLogic)
    {
        $this->passengerRepository = $passengerRepository;
        $this->tripLogic = $tripLogic;
    }
    
    public function getPassengers($tripId, $user, $data)
    {
        if (!$this->tripLogic->tripOwner($user, $tripId) || $this->isUserRequestAccepted($tripId, $user->id)) {
            $this->setErrors(['error' => 'access_denied']);

            return;
        }
        return $this->passengerRepository->getPassengers($tripId, $user, $data);
    }
    
    public function getPendingRequests($tripId, $user, $data)
    {
        if (!$this->tripLogic->tripOwner($user, $tripId)) {
            $this->setErrors(['error' => 'access_denied']);

            return;
        }
        return $this->passengerRepository->getPendingRequests($tripId, $user, $data);
    }
    
    private function validateInput($input)
    {
        return Validator::make($input, [
            'user_id' => 'required|numeric',
            'trip_id' => 'required|numeric',
        ]);
    }

    private function isInputValid($input)
    {
        $validation = $this->validateInput($input);
        
        if ($result = $validation->fails()) {
            $this->setErrors($validation->errors());
        }

        return true;
    }
    
    public function newRequest($tripId, $user, $data = [])
    {
        $userId = $user->id;

        $input = [
            'trip_id' => $tripId,
            'user_id' => $userId
        ];
        
        if (!$this->isInputValid($input)) {
            return;
        }

        if ($trip = $this->tripLogic->show($user, $tripId)) {
            if ($result = $this->passengerRepository->newRequest($tripId, $user, $data)) {
                event(new RequestEvent($tripId, $user->id, $trip->user_id));
            }
            return $result;
        } else {
            $this->setErrors(['error' => 'access_denied']);

            return;
        }
    }
    
    public function cancelRequest($tripId, $cancelUserId, $user, $data = [])
    {
        $userId = $user->id;

        $input = [
            'trip_id' => $tripId,
            'user_id' => $user->id
        ];
        
        if (!$this->isInputValid($input)) {
            return;
        }
        
        $trip = $this->tripLogic->show($user, $tripId);

        if (($this->isUserRequestPending($tripId, $userId) && $cancelUserId == $user->id)
            || $this->isUserRequestAccepted($tripId, $userId)) {
            if ($result = $this->passengerRepository->cancelRequest($tripId, $user, $data)) {
                if ($trip->user_id == $user->id) {
                    event(new CancelEvent($tripId, $user->id, $cancelUserId));
                } else {
                    event(new CancelEvent($tripId, $cancelUserId, $trip->user_id));
                }
            }
            
            return $result;
        } else {
            $this->setErrors(['error' => 'not_a_passenger']);

            return;
        }
    }
    
    public function acceptRequest($tripId, $acceptedUserId, $user, $data = [])
    {
        $input = [
            'trip_id' => $tripId,
            'user_id' => $acceptedUserId
        ];
        
        if (!$this->isInputValid($input)) {
            return;
        }
        
        $trip = $this->tripLogic->show($user, $tripId);
        if ($this->isUserRequestPending($tripId, $acceptedUserId) && $this->tripLogic->tripOwner($user, $trip)) {
            if ($trip->seats_available == 0) {
                $this->setErrors(['error' => 'not_seat_available']);

                return;
            }

            if ($result = $this->passengerRepository->acceptRequest($tripId, $acceptedUserId, $user, $data)) {
                event(new AcceptEvent($tripId, $trip->user_id, $user->id));
            }
            return $result;
        } else {
            $this->setErrors(['error' => 'not_valid_request']);

            return;
        }
    }
    
    public function rejectRequest($tripId, $rejectedUserId, $user, $data = [])
    {
        $input = [
            'trip_id' => $tripId,
            'user_id' => $rejectedUserId
        ];
        
        if (!$this->isInputValid($input)) {
            return;
        }
         
        $trip = $this->tripLogic->show($user, $tripId);
        if (!$this->isUserRequestPending($tripId, $rejectedUserId) || !$this->tripLogic->tripOwner($user, $trip)) {
            $this->setErrors(['error' => 'not_valid_request']);

            return;
        }

        if ($result = $this->passengerRepository->rejectRequest($tripId, $rejectedUserId, $user, $data)) {
            event(new RejectEvent($tripId, $trip->user_id, $user->id));
        }
        return $result;
    }

    public function isUserRequestAccepted($tripId, $userId)
    {
        return $this->passengerRepository->isUserRequestAccepted($tripId, $userId);
    }

    public function isUserRequestRejected($tripId, $userId)
    {
        return $this->passengerRepository->isUserRequestRejected($tripId, $userId);
    }

    public function isUserRequestPending($tripId, $userId)
    {
        return $this->passengerRepository->isUserRequestPending($tripId, $userId);
    }
}
