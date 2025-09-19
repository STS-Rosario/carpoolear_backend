<?php

namespace STS\Http\Controllers\Api\v1;

use Illuminate\Http\Request;
use STS\Http\Controllers\Controller;
use STS\Http\ExceptionWithErrors;
use STS\Services\Logic\TripsManager;
use STS\Transformers\TripTransformer;
use STS\Repository\TripSearchRepository;

class TripController extends Controller
{
    protected $user;

    protected $tripsLogic;
    protected $tripSearchRepository;

    public function __construct(Request $r, TripsManager $tripsLogic, TripSearchRepository $tripSearchRepository)
    {
        $this->middleware('logged')->except(['search']);
        $this->middleware('logged.optional')->only('search');
        $this->tripsLogic = $tripsLogic;
        $this->tripSearchRepository = $tripSearchRepository;
    }

    public function create(Request $request)
    {
        $this->user = auth()->user();
        $data = $request->all();
        $trip = $this->tripsLogic->create($this->user, $data);
        if (! $trip) {
            throw new ExceptionWithErrors('Could not create new trip.', $this->tripsLogic->getErrors());
        }

        return $this->item($trip, new TripTransformer($this->user));
        //return response()->json(['data' => $trip]);
    }

    public function update($id, Request $request)
    {
        $this->user = auth()->user();
        $data = $request->all();
        $trip = $this->tripsLogic->update($this->user, $id, $data);
        if (! $trip) {
            throw new ExceptionWithErrors('Could not update trip.', $this->tripsLogic->getErrors());
        }

        return $this->item($trip, new TripTransformer($this->user));
        //return response()->json(['data' => $trip]);
    }

    public function changeTripSeats($id, Request $request)
    {
        $this->user = auth()->user();
        $increment = $request->get('increment');
        $trip = $this->tripsLogic->changeTripSeats($this->user, $id, $increment);
        if (! $trip) {
            throw new ExceptionWithErrors('Could not update trip.', $this->tripsLogic->getErrors());
        }

        return $this->item($trip, new TripTransformer($this->user));
    }

    public function delete($id, Request $request)
    {
        $this->user = auth()->user();
        $result = $this->tripsLogic->delete($this->user, $id);
        if (! $result) {
            throw new ExceptionWithErrors('Could not delete trip.', $this->tripsLogic->getErrors());
        }

        return response()->json(['data' => 'ok']);
    }

    public function show($id, Request $request)
    {
        $this->user = auth()->user();
        $trip = $this->tripsLogic->show($this->user, $id);
        if (! $trip) {
            throw new ExceptionWithErrors('Could not found trip.', $this->tripsLogic->getErrors());
        }

        return $this->item($trip, new TripTransformer($this->user));
        //return response()->json(['data' => $trip]);
    }

    public function search(Request $request)
    {
        $data = $request->all();

        if (!isset($data['page_size'])) {
            $data['page_size'] = 20;
        }

        $this->user = auth('api')->user();
        $trips = $this->tripsLogic->search($this->user, $data);
        
        // Track the search for advertising purposes
        $this->trackSearch($data, $trips);
        
        /// return $trips;
        return $this->paginator($trips, new TripTransformer($this->user));
    }

    private function trackSearch($data, $trips)
    {
        try {
            $originId = isset($data['origin_id']) ? $data['origin_id'] : null;
            $destinationId = isset($data['destination_id']) ? $data['destination_id'] : null;
            $searchDate = isset($data['date']) ? $data['date'] : null;
            $isPassenger = isset($data['is_passenger']) ? parse_boolean($data['is_passenger']) : false;
            
            // Track searches with either origin, destination, or both
            if ($originId || $destinationId) {
                $clientPlatform = 0; // Default to web for now
                $this->tripSearchRepository->trackSearch($this->user, $originId, $destinationId, $trips, $clientPlatform, $searchDate, $isPassenger);
            }
        } catch (\Exception $e) {
            // Log error but don't break the search functionality
            \Log::error('Error tracking trip search: ' . $e->getMessage());
        }
    }

    public function getTrips(Request $request)
    {
        $this->user = auth()->user();

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
        //return response()->json(['data' => $trips]);
    }

    public function getOldTrips(Request $request)
    {
        $this->user = auth()->user();

        
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
        $distance = isset($data['distance']) ? $data['distance'] : null;

        
        return $this->tripsLogic->price($from, $to, $distance);       
    }

    public function getTripInfo(Request $request)
    {
        $data = $request->all();
        $points = isset($data['points']) ? $data['points'] : null;
        return $this->tripsLogic->getTripInfo($points);
    }

    public function selladoViaje(Request $request)
    {
        $this->user = auth()->user();

        $infoSelladoViaje = $this->tripsLogic->selladoViaje($this->user);

        return response()->json([
            'success' => true, 
            'data' => $infoSelladoViaje
        ]);
    }

    public function changeVisibility($id, Request $request) 
    {
        $this->user = auth()->user();
        $trip = $this->tripsLogic->changeVisibility($this->user, $id);
        if (! $trip) {
            throw new ExceptionWithErrors('Could not update trip.', $this->tripsLogic->getErrors());
        }

        return $this->item($trip, new TripTransformer($this->user));
    }
}
