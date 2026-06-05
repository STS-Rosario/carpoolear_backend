<?php

namespace STS\Repository;

use STS\Models\Car as CarModel;
use STS\Models\User as UserModel;

class CarsRepository
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

    public function getUserCar($userId)
    {
        return CarModel::where('user_id', $userId)->first();
    }

    public function findByUserAndPatenteIncludingTrashed($userId, $patente)
    {
        return CarModel::withTrashed()
            ->where('user_id', $userId)
            ->where('patente', $patente)
            ->first();
    }
}
