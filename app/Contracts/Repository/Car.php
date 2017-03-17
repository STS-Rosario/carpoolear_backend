<?php

namespace STS\Contracts\Repository;

use STS\Entities\Car as CarModel;
use STS\User as UserModel;

interface Car
{
    public function create(CarModel $car);

    public function update(CarModel $car);

    public function show($id);

    public function delete(CarModel $car);

    public function index(UserModel $user);
}
