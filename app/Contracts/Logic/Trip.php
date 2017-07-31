<?php

namespace STS\Contracts\Logic;

interface Trip
{
    public function create($user, array $data);

    public function update($user, $trip_id, array $data);

    public function delete($user, $trip_id);

    public function show($user, $trip);

    public function index($data);

    public function search($user, $data);

    public function myTrips($user, $asDriver);

    public function userCanSeeTrip($user, $trip);

    public function tripOwner($user, $trip_id);
}
