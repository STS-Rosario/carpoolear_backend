<?php

namespace STS\Http\Controllers\Api\v1;

use Auth;
use Illuminate\Http\Request;
use STS\Http\Controllers\Controller;
use STS\Contracts\Logic\IPassengersLogic;

class PassengerController extends Controller
{
    protected $user;
    protected $passengerLogic;

    public function __construct(Request $r, IPassengersLogic $passengerLogic)
    {
        $this->middleware('api.auth');
        $this->passengerLogic = $passengerLogic;
        $this->user = $this->auth->user();
    }

    public function passengers($tripId, Request $request)
    {
        $data = $request->all();

        $passengers = $this->passengerLogic->index($tripId, $this->user, $data);

        return $passengers;
    }

    public function requests($tripId, Request $request)
    {
        $data = $request->all();

        $passengers = $this->passengerLogic->getPendingRequests($tripId, $this->user, $data);

        return $passengers;
    }

    public function allRequests(Request $request)
    {
        $data = $request->all();

        $passengers = $this->passengerLogic->getPendingRequests(null, $this->user, $data);

        return $passengers;
    }

    public function newRequest($tripId, Request $request)
    {
        $data = $request->all();

        $request = $this->passengerLogic->newRequest($tripId, $this->user, $data);

        if (! $request) {
            throw new StoreResourceFailedException('Could not create new request.', $this->passengerLogic->getErrors());
        }

        return $this->response->withArray(['data' => $request]);
    }

    public function cancelRequest($tripId, $userId, Request $request)
    {
        $data = $request->all();

        $request = $this->passengerLogic->cancelRequest($tripId, $userId, $this->user, $data);

        if (! $request) {
            throw new StoreResourceFailedException('Could not cancel request.', $this->passengerLogic->getErrors());
        }

        return $this->response->withArray(['data' => $request]);
    }

    public function acceptRequest($tripId, $userId, Request $request)
    {
        $data = $request->all();

        $request = $this->passengerLogic->acceptRequest($tripId, $userId, $this->user, $data);

        if (! $request) {
            throw new StoreResourceFailedException('Could not accept request.', $this->passengerLogic->getErrors());
        }

        return $this->response->withArray(['data' => $request]);
    }

    public function rejectRequest($tripId, $userId, Request $request)
    {
        $data = $request->all();

        $request = $this->passengerLogic->rejectRequest($tripId, $userId, $this->user, $data);

        if (! $request) {
            throw new StoreResourceFailedException('Could not accept request.', $this->passengerLogic->getErrors());
        }

        return $this->response->withArray(['data' => $request]);
    }
}
