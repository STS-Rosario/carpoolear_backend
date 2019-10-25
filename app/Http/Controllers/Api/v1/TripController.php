<?php

namespace STS\Http\Controllers\Api\v1;

use Illuminate\Http\Request;
use STS\Http\Controllers\Controller;
use STS\Transformers\TripTransformer;
use STS\Contracts\Logic\Trip as TripLogic;
use Dingo\Api\Exception\StoreResourceFailedException;
use Carbon\Carbon;

class TripController extends Controller
{
    protected $user;

    protected $tripsLogic;

    public function __construct(Request $r, TripLogic $tripsLogic)
    {
        $this->middleware('logged', ['except' => ['search']]);
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

        return $this->item($trip, new TripTransformer($this->user), ['key' => 'data']);
        //return $this->response->withArray(['data' => $trip]);
    }

    public function update($id, Request $request)
    {
        $this->user = $this->auth->user();
        $data = $request->all();
        $trip = $this->tripsLogic->update($this->user, $id, $data);
        if (! $trip) {
            throw new StoreResourceFailedException('Could not update trip.', $this->tripsLogic->getErrors());
        }

        return $this->item($trip, new TripTransformer($this->user), ['key' => 'data']);
        //return $this->response->withArray(['data' => $trip]);
    }

    public function changeTripSeats($id, Request $request)
    {
        $this->user = $this->auth->user();
        $increment = $request->get('increment');
        $trip = $this->tripsLogic->changeTripSeats($this->user, $id, $increment);
        if (! $trip) {
            throw new StoreResourceFailedException('Could not update trip.', $this->tripsLogic->getErrors());
        }

        return $this->item($trip, new TripTransformer($this->user), ['key' => 'data']);
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
            throw new StoreResourceFailedException('Could not found trip.', $this->tripsLogic->getErrors());
        }

        return $this->item($trip, new TripTransformer($this->user), ['key' => 'data']);
        //return $this->response->withArray(['data' => $trip]);
    }

    public function search(Request $request)
    {
        $data = $request->all();

        if (!isset($data['page_size'])) {
            $data['page_size'] = 20;
        }

        $this->user = $this->auth->user();
        $trips = $this->tripsLogic->search($this->user, $data);
        return $this->response->paginator($trips, new TripTransformer($this->user));
    }

    public function getTrips(Request $request)
    {
        $this->user = $this->auth->user();

        if ($request->has('as_driver')) {
            $asDriver = parse_boolean($request->get('as_driver'));
        } else {
            $asDriver = true;
        }
        if ($request->has('user_id')  && $this->user->is_admin) {
            $trips = $this->tripsLogic->getTrips($this->user,$request->get('user_id'), $asDriver);
        } else {
            $trips = $this->tripsLogic->getTrips($this->user,$this->user->id, $asDriver);
        }

        return $this->collection($trips, new TripTransformer($this->user));
        //return $this->response->withArray(['data' => $trips]);
    }

    public function getOldTrips(Request $request)
    {
        $this->user = $this->auth->user();

        
        if ($request->has('as_driver')) {
            $asDriver = parse_boolean($request->get('as_driver'));
        } else {
            $asDriver = true;
        }
        
        if ($request->has('user_id')) {
            $trips = $this->tripsLogic->getOldTrips($this->user,$request->get('user_id'), $asDriver);
        } else {
            $trips = $this->tripsLogic->getOldTrips($this->user,$this->user->id, $asDriver);
        }

        return $this->collection($trips, new TripTransformer($this->user));
    }

    public function price(Request $request) 
    {
        $data = $request->all();

        $from = isset($data['from']) ? $data['from'] : null;
        $to = isset($data['to']) ? $data['to'] : null;
        $distance = isset($data['distance']) ? $data['ditance'] : null;

        return $this->tripsLogic->price($from, $to, $distance);       
    }
}
