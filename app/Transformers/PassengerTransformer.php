<?php

namespace STS\Transformers;

use STS\Models\Passenger;
use League\Fractal\TransformerAbstract;

class PassengerTransformer extends TransformerAbstract
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
    public function transform(Passenger $passenger)
    {
        $data = [
            'id' => $passenger->id,
            'trip_id' => $passenger->trip_id,
            'created_at' => $passenger->created_at->toDateTimeString(),
            'state' => $passenger->request_state,
        ];

        $userTransform = new TripUserTransformer($this->user);
        $data['user'] = $userTransform->transform($passenger->user);

        return $data;
    }
}
