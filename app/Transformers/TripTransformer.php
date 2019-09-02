<?php

namespace STS\Transformers;

use STS\Entities\Trip;
use League\Fractal\TransformerAbstract;

class TripTransformer extends TransformerAbstract
{
    protected $user;

    public function __construct($user)
    {
        $this->user = $user;
    }

    /**
     * Turn this item object into a generic array.
     *
     * @return array
     */
    public function transform(Trip $trip)
    {
        $data = [
            'id' => $trip->id,
            'from_town' => $trip->from_town,
            'to_town' => $trip->to_town,
            'trip_date' => $trip->trip_date->toDateTimeString(),
            'description' => $trip->description,
            'total_seats' => $trip->total_seats,
            'friendship_type_id' => $trip->friendship_type_id,
            'distance' => $trip->distance,
            'estimated_time' => $trip->estimated_time,
            'is_passenger' => $trip->is_passenger,
            'passenger_count' => $trip->passenger_count,
            'seats_available' => $trip->seats_available,
            'points' => $trip->points,
            'updated_at' => $trip->updated_at->toDateTimeString(),
        ];

        if ($trip->deleted_at) {
            $data['deleted'] = true;
        }

        $data['request'] = '';
        $data['passenger'] = [];
        if ($this->user) {
            $userTranforms = new TripUserTransformer($this->user);
            $data['user'] = $userTranforms->transform($trip->user);
            if ($trip->isPassenger($this->user) || $trip->user_id == $this->user->id || $this->user->is_admin) {
                foreach ($trip->passengerAccepted as $passenger) {
                    $data['passenger'][] = $userTranforms->transform($passenger->user);
                }
                $data['car'] = $trip->car;
                $data['request_count'] = count($trip->passenger);
            } elseif ($trip->isPending($this->user)) {
                $data['request'] = 'send';
            }
        }

        return $data;
    }
}
