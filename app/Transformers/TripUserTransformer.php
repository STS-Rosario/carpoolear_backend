<?php

namespace STS\Transformers;

use STS\User;
use League\Fractal\TransformerAbstract;

class TripUserTransformer extends TransformerAbstract
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
    public function transform(User $user)
    {
        $data = [
            'id' => $user->id,
            'name' => $user->name,
            //'email' => $user->email,
            'descripcion' => $user->descripcion,
            'image' => $user->image,
            'positive_ratings' => $user->positive_ratings,
            'negative_ratings' => $user->negative_ratings,
            'last_connection' => $user->last_connection->toDateTimeString(),
            'accounts' => $user->accounts,
            'has_pin' => $user->has_pin,
            'is_member' => $user->is_member,
        ];

        return $data;
    }
}
