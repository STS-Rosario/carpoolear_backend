<?php

namespace STS\Http\Controllers\Api\v1;

use Auth;
use Illuminate\Http\Request;
use STS\Http\Controllers\Controller;
use STS\Contracts\Logic\Trip as TripLogic;

class TripController extends Controller
{
    protected $user;
    protected $tripsLogic;

    public function __construct(Request $r, TripLogic $tripsLogic)
    {
        $this->middleware('api.auth', ['except' => ['index']]);
        $this->tripsLogic = $tripsLogic;
    }

    public function create(Request $request)
    {
        $this->user = $this->auth->user();
        $data = $request->all();
        $trip = $this->tripsLogic->create($this->user, $data);
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

    public function index(Request $request)
    {
        $this->user = $this->auth->user();
        $data = $request->all();
        $trips = $this->tripsLogic->index($this->user, $data);

        return $trips;
    }
}
