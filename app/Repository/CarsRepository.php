<?php

namespace STS\Repository;

use STS\User as UserModel;
use STS\Entities\Car as CarModel;
use STS\Contracts\Repository\Car as CarRepository;

class CarsRepository implements CarRepository
{
    public function create(CarModel $car)
    {
        return $car->save();
    }

    public function update(CarModel $car)
    {
        return $car->save();
    }

    public function show($id)
    {
        return CarModel::find($id);
    }

    public function delete(CarModel $car)
    {
        return $car->delete();
    }

    public function index(UserModel $user)
    {
        return $user->cars;
    }
}
