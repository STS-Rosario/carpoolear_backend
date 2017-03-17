<?php

namespace STS\Contracts\Repository;

interface Trip
{
    public function create(array $data);

    public function update($trip, array $data);

    public function show($id);

    public function index($user, $data);

    public function delete($trip);

    public function addPoints($trip, $points);

    public function deletePoints($trip, $points);
}
