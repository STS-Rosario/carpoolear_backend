<?php

namespace STS\Http\Controllers\Api\v1;

use Illuminate\Http\Request;
use STS\Http\Controllers\Controller;
use STS\Contracts\Logic\IPassengersLogic;
use STS\Transformers\PassengerTransformer;
use Dingo\Api\Exception\StoreResourceFailedException;

class PassengerController extends Controller
{
    protected $user;
    protected $passengerLogic;

    public function __construct(Request $r, IPassengersLogic $passengerLogic)
    {
        $this->middleware('logged');
        $this->passengerLogic = $passengerLogic;
    }

    public function passengers($tripId, Request $request)
    {
        $this->user = $this->auth->user();
        $data = $request->all();

        $passengers = $this->passengerLogic->index($tripId, $this->user, $data);

        return $this->response->collection($passengers, new PassengerTransformer($this->user));
    }

    public function requests($tripId, Request $request)
    {
        $this->user = $this->auth->user();
        $data = $request->all();

        $passengers = $this->passengerLogic->getPendingRequests($tripId, $this->user, $data);

        return $this->response->collection($passengers, new PassengerTransformer($this->user));
    }

    public function allRequests(Request $request)
    {
        $this->user = $this->auth->user();
        $data = $request->all();

        $passengers = $this->passengerLogic->getPendingRequests(null, $this->user, $data);

        return $this->response->collection($passengers, new PassengerTransformer($this->user));
    }

    public function newRequest($tripId, Request $request)
    {
        $this->user = $this->auth->user();
        $data = $request->all();

        $request = $this->passengerLogic->newRequest($tripId, $this->user, $data);

        if (! $request) {
            throw new StoreResourceFailedException('Could not create new request.', $this->passengerLogic->getErrors());
        }

        return $this->response->withArray(['data' => $request]);
    }

    public function cancelRequest($tripId, $userId, Request $request)
    {
        $this->user = $this->auth->user();
        $data = $request->all();

        $request = $this->passengerLogic->cancelRequest($tripId, $userId, $this->user, $data);

        if (! $request) {
            throw new StoreResourceFailedException('Could not cancel request.', $this->passengerLogic->getErrors());
        }

        return $this->response->withArray(['data' => $request]);
    }

    public function acceptRequest($tripId, $userId, Request $request)
    {
        $this->user = $this->auth->user();
        $data = $request->all();

        $request = $this->passengerLogic->acceptRequest($tripId, $userId, $this->user, $data);

        if (! $request) {
            throw new StoreResourceFailedException('Could not accept request.', $this->passengerLogic->getErrors());
        }

        return $this->response->withArray(['data' => $request]);
    }

    public function rejectRequest($tripId, $userId, Request $request)
    {
        $this->user = $this->auth->user();
        $data = $request->all();

        $request = $this->passengerLogic->rejectRequest($tripId, $userId, $this->user, $data);

        if (! $request) {
            throw new StoreResourceFailedException('Could not accept request.', $this->passengerLogic->getErrors());
        }

        return $this->response->withArray(['data' => $request]);
    }
}
