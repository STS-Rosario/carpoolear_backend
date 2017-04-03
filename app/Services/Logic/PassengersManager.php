<?php

namespace STS\Services\Logic;

use Validator;

use STS\User;
use STS\Entities\Passanger;
use STS\Contracts\Logic\IPassengersLogic;
use STS\Contracts\Repository\IPassengersRepository;
use STS\Contracts\Logic\Trip as TripLogic;

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

    private function isInputValid($input) {
        $validation = $this->validateInput($input);
        
        if($result = $validation->fails()){
            $this->setErrors($validation->errors());
        }
        
        return $result;
    }
    
    public function newRequest($tripId, $user, $data)
    {
        $userId = $user->id;

        $input = [
            'trip_id' => $tripId,
            'user_id' => $userId
        ];
        
        if(!$this->isInputValid($input)){
            return;
        }

        //TODO: validar que el usuario pueda ver el viaje
        return $this->passengerRepository->newRequest($tripId, $user, $data);
    }
    
    public function cancelRequest($tripId, $user, $data)
    {
        $userId = $user->id;

        $input = [
            'trip_id' => $tripId,
            'user_id' => $user->id
        ];
        
        if(!$this->isInputValid($input)){
            return;
        }

        //TODO: validar que el usuario este como pasajero del viaje
        if(!$this->isUserRequestPending($tripId, $userId) || !$this->isUserRequestAccepted($tripId, $userId))
        {
            //throw new
        }
        
        return $this->passengerRepository->cancelRequest($tripId, $user, $data);
    }
    
    public function acceptRequest($tripId, $acceptedUserId, $user, $data)
    {
        $input = [
            'trip_id' => $tripId,
            'user_id' => $acceptedUserId
        ];
        
        if(!$this->isInputValid($input)){
            return;
        }
        
        //TODO: validar que el user sea el dueño del viaje
        
        //TODO: validar que el acceptedUserId este como pasajero del viaje
        if(!$this->isUserRequestPending($tripId, $acceptedUserId))
        {
            //throw new
        }

        return $this->passengerRepository->acceptRequest($tripId, $acceptedUserId, $user, $data);
    }
    
    public function rejectRequest($tripId, $rejectedUserId, $user, $data)
    {
        $input = [
            'trip_id' => $tripId,
            'user_id' => $rejectedUserId
        ];
        
        if(!$this->isInputValid($input)){
            return;
        }
        
        //TODO: validar que el user sea el dueño del viaje
        
        //TODO: validar que el rejectedUserId este como pasajero del viaje
        if(!$this->isUserRequestPending($tripId, $acceptedUserId))
        {
            //throw new
        }

        return $this->passengerRepository->rejectRequest($tripId, $rejectedUserId, $user, $data);
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