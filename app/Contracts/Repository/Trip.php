<?php

namespace STS\Contracts\Repository;

interface Trip
{
    public function create(array $data);

    public function update($trip, array $data);

    public function show($user, $id);

    public function index($data);

    public function search($user, $data);

    public function delete($trip);

    public function addPoints($trip, $points);

    public function deletePoints($trip);

    public function getTrips($user, $userId, $asDriver);
}
