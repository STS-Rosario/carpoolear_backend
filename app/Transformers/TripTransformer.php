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
            'seat_price' => $trip->seat_price,
            'is_passenger' => $trip->is_passenger,
            'passenger_count' => $trip->passenger_count,
            'seats_available' => $trip->seats_available,
            'points' => $trip->points,
            'ratings' => $trip->ratings,
            'updated_at' => $trip->updated_at->toDateTimeString(),
            'allow_kids' => $trip->allow_kids,
            'allow_animals' => $trip->allow_animals,
            'allow_smoking' => $trip->allow_smoking
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
                $data['passenger'] = $trip->passenger;
                $data['car'] = $trip->car;
                $data['request_count'] = count($trip->passenger);
                $data['passengerAccepted_count'] = count($trip->passengerAccepted);
                if (count($trip->passenger) > 0) {
                    foreach ($trip->passenger as $prequest) {
                        $prequest->request_id = $prequest->id;
                        $prequest->id = $prequest->user->id;
                        $prequest->name = $prequest->user->name;
                        $prequest->email = $prequest->user->email;
                    }
                }
            } elseif ($trip->isPending($this->user)) {
                $data['request'] = 'send';
            }
            // passengerPending
            $data['passengerPending_count'] = count($trip->passengerPending);

        }

        return $data;
    }
}
