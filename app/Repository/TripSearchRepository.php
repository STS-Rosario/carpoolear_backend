<?php

namespace STS\Repository;

use STS\Models\TripSearch;
use STS\Models\Trip;
use STS\Models\Passenger;
use Carbon\Carbon;

class TripSearchRepository
{
    public function create($data)
    {
        return TripSearch::create($data);
    }

    public function trackSearch($user, $originId, $destinationId, $trips, $clientPlatform = 0, $searchDate = null, $isPassenger = false)
    {
        // Calculate total trips
        $amountTrips = $trips->total() ?? $trips->count();
        
        // Calculate carpooled trips (trips with no available seats)
        $amountTripsCarpooleados = 0;
        
        if ($trips->count() > 0) {
            $amountTripsCarpooleados = $trips->filter(function ($trip) {
                return $trip->seats_available <= 0;
            })->count();
        }

        $searchData = [
            'user_id' => $user ? $user->id : null,
            'origin_id' => $originId,
            'destination_id' => $destinationId,
            'search_date' => $searchDate ? Carbon::parse($searchDate) : Carbon::now(),
            'amount_trips' => $amountTrips,
            'amount_trips_carpooleados' => $amountTripsCarpooleados,
            'client_platform' => $clientPlatform,
            'is_passenger' => $isPassenger,
            'results_json' => [] // Empty for now as requested
        ];

        return $this->create($searchData);
    }
} 