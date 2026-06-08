<?php

namespace STS\Transformers;

use League\Fractal\TransformerAbstract;
use STS\Models\Passenger;

class PassengerSeatRequestTransformer extends TransformerAbstract
{
    protected $user;

    public function __construct($user)
    {
        $this->user = $user;
    }

    /**
     * @return array
     */
    public function transform(Passenger $passenger)
    {
        $tripTransformer = new TripTransformer($this->user);

        return [
            'id' => $passenger->id,
            'trip_id' => $passenger->trip_id,
            'request_state' => $passenger->request_state,
            'created_at' => $passenger->created_at->toDateTimeString(),
            'trip' => $tripTransformer->transform($passenger->trip),
        ];
    }
}
