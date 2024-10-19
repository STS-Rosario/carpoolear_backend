<?php

namespace STS\Http\Controllers\Api\v1;

use Illuminate\Http\Request;
use STS\Http\Controllers\Controller; 
use STS\Http\ExceptionWithErrors;
use STS\Services\Logic\PassengersManager;
use STS\Transformers\PassengerTransformer;

class PassengerController extends Controller
{
    protected $user;

    protected $passengerLogic;

    public function __construct(Request $r, PassengersManager $passengerLogic)
    {
        $this->middleware('logged');
        $this->passengerLogic = $passengerLogic;
    }

    public function passengers($tripId, Request $request)
    {
        $this->user = auth()->user();
        $data = $request->all();

        $passengers = $this->passengerLogic->index($tripId, $this->user, $data);

        return $this->collection($passengers, new PassengerTransformer($this->user));
    }

    public function requests($tripId, Request $request)
    {
        $this->user = auth()->user();
        $data = $request->all();

        $passengers = $this->passengerLogic->getPendingRequests($tripId, $this->user, $data);

        return $this->collection($passengers, new PassengerTransformer($this->user));
    }

    public function allRequests(Request $request)
    {
        $this->user = auth()->user();
        $data = $request->all();

        $passengers = $this->passengerLogic->getPendingRequests(null, $this->user, $data);

        return $this->collection($passengers, new PassengerTransformer($this->user));
    }

    public function paymentPendingRequest(Request $request)
    {
        $this->user = auth()->user();
        $data = $request->all();

        $toPay = $this->passengerLogic->getPendingPaymentRequests(null, $this->user, $data);

        return $this->collection($toPay, new PassengerTransformer($this->user));
    }

    public function newRequest($tripId, Request $request)
    {
        $this->user = auth()->user();
        $data = $request->all();

        $request = $this->passengerLogic->newRequest($tripId, $this->user, $data);

        if (! $request) {
            throw new ExceptionWithErrors('Could not create new request.', $this->passengerLogic->getErrors());
        }

        return response()->json(['data' => $request]);
    }

    public function transactions(Request $request) {
        $user = auth()->user();
        return $this->passengerLogic->transactions($user);

    }
    public function cancelRequest($tripId, $userId, Request $request)
    {
        $this->user = auth()->user();
        $data = $request->all();

        $request = $this->passengerLogic->cancelRequest($tripId, $userId, $this->user, $data);

        if (!$request) {
            throw new ExceptionWithErrors('Could not cancel request.', $this->passengerLogic->getErrors());
        }

        return response()->json(['data' => $request]);
    }

    public function acceptRequest($tripId, $userId, Request $request)
    {
        $this->user = auth()->user();
        $data = $request->all();

        $request = $this->passengerLogic->acceptRequest($tripId, $userId, $this->user, $data);

        if (! $request) {
            throw new ExceptionWithErrors('Could not accept request.', $this->passengerLogic->getErrors());
        }

        return response()->json(['data' => $request]);
    }


    public function payRequest($tripId, $userId, Request $request)
    {
        $this->user = auth()->user();
        $data = $request->all();

        $request = $this->passengerLogic->payRequest($tripId, $userId, $this->user, $data);

        if (! $request) {
            throw new ExceptionWithErrors('Could not accept request.', $this->passengerLogic->getErrors());
        }

        return response()->json(['data' => $request]);
    }

    public function rejectRequest($tripId, $userId, Request $request)
    {
        $this->user = auth()->user();
        $data = $request->all();

        $request = $this->passengerLogic->rejectRequest($tripId, $userId, $this->user, $data);

        if (! $request) {
            throw new ExceptionWithErrors('Could not accept request.', $this->passengerLogic->getErrors());
        }

        return response()->json(['data' => $request]);
    }
}
