<?php

namespace STS\Http\Controllers\Api\v1;

use Auth;
use Illuminate\Http\Request;
use STS\Http\Controllers\Controller;
use STS\Contracts\Logic\Trip as TripLogic;

class PassengerController extends Controller
{
    protected $user;
    protected $passengerLogic;
    
    public function __construct(Request $r, PassengerLogic $passengerLogic)
    {
        $this->middleware('api.auth', ['except' => ['index']]);
        $this->passengerLogic = $passengerLogic;
    }
    
    public function passengers($tripId, Request $request)
    {
        $this->user = $this->auth->user();

        $data = $request->all();

        $passengers = $this->passengerLogic->index($tripId, $this->user, $data);
        
        return $passengers;
    }

    public function requests($tripId, Request $request)
    {
        $this->user = $this->auth->user();

        $data = $request->all();

        $passengers = $this->passengerLogic->requests($tripId, $this->user, $data);
        
        return $passengers;
    }

    public function newRequest($tripId, Request $request)
    {
        $this->user = $this->auth->user();

        $data = $request->all();

        $request = $this->passengerLogic->newRequest($tripId, $this->user, $data);

        if(!$request) {
            throw new StoreResourceFailedException('Could not create new request.', $this->passengerLogic->getErrors());
        }

         return $this->response->withArray(['data' => $request]);
    }

    public function cancelRequest($tripId, Request $request)
    {
        $this->user = $this->auth->user();

        $data = $request->all();

        $request = $this->passengerLogic->cancelRequest($tripId, $this->user, $data);

        if(!$request) {
            throw new StoreResourceFailedException('Could not cancel request.', $this->passengerLogic->getErrors());
        }

         return $this->response->withArray(['data' => $request]);
    }

    public function acceptRequest($tripId, $userId, Request $request)
    {
        $this->user = $this->auth->user();

        $data = $request->all();

        $request = $this->passengerLogic->acceptRequest($tripId, $userId, $this->user, $data);

        if(!$request) {
            throw new StoreResourceFailedException('Could not accept request.', $this->passengerLogic->getErrors());
        }

         return $this->response->withArray(['data' => $request]);
    }

    public function rejectRequest($tripId, $userId, Request $request)
    {
        $this->user = $this->auth->user();

        $data = $request->all();

        $request = $this->passengerLogic->rejectRequest($tripId, $userId, $this->user, $data);

        if(!$request) {
            throw new StoreResourceFailedException('Could not accept request.', $this->passengerLogic->getErrors());
        }

         return $this->response->withArray(['data' => $request]);
    }

    public function create(Request $request)
    {
        $this->user = $this->auth->user();
        $data = $request->all();
        $trip = $this->passengerLogic->create($this->user, $data);
        if (! $trip) {
            throw new StoreResourceFailedException('Could not create new trip.', $this->tripsLogic->getErrors());
        }
        
        return $this->response->withArray(['data' => $trip]);
    }
    
    public function update($id, Request $request)
    {
        $this->user = $this->auth->user();
        $data = $request->all();
        $trip = $this->tripsLogic->update($this->user, $id, $data);
        if (! $trip) {
            throw new StoreResourceFailedException('Could not update trip.', $this->tripsLogic->getErrors());
        }
        
        return $this->response->withArray(['data' => $trip]);
    }
    
    public function delete($id, Request $request)
    {
        $this->user = $this->auth->user();
        $result = $this->tripsLogic->delete($this->user, $id);
        if (! $result) {
            throw new StoreResourceFailedException('Could not delete trip.', $this->tripsLogic->getErrors());
        }
        
        return $this->response->withArray(['data' => 'ok']);
    }
    
    public function show($id, Request $request)
    {
        $this->user = $this->auth->user();
        $trip = $this->tripsLogic->show($this->user, $id);
        if (! $trip) {
            throw new ResourceException('Could not found trip.', $this->tripsLogic->getErrors());
        }
        
        return $this->response->withArray(['data' => $trip]);
    }
}