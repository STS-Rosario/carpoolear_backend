<?php

namespace STS\Contracts\Repository;

interface IRatingRepository
{
    public function getRating($user_from_id, $user_to_id, $trip_id);

    public function getRatings($user, $data = []);

    public function getPendingRatings($user);

    public function find($id);

    public function findBy($key, $value);

    public function create($user_from_id, $user_to_id, $trip_id, $user_to_type, $user_to_state, $hash);

    public function update($rateModel);
}
