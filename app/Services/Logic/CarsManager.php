<?php

namespace STS\Services\Logic;

use STS\Repository\CarsRepository;
use Validator;
use STS\Models\User as UserModel;
use STS\Models\Car as CarModel;

class CarsManager extends BaseManager
{
    protected $repo;

    public function __construct(CarsRepository $carsRepo)
    {
        $this->repo = $carsRepo;
    }

    public function validator(array $data, $userId = null, $carId = null)
    {
        $rules = [
            'patente'     => 'required|string|max:10',
            'description' => 'required|string|max:255',
        ];

        // Add unique validation for patente per user
        if ($userId) {
            $rules['patente'] .= '|unique:cars,patente,NULL,id,user_id,' . $userId;
        }

        // If updating, ignore current car's patente
        if ($carId) {
            $rules['patente'] = 'required|string|max:10|unique:cars,patente,' . $carId . ',id,user_id,' . $userId;
        }

        return Validator::make($data, $rules);
    }

    public function create(UserModel $user, $data)
    {
        // Check if user already has a car
        $existingCar = $this->repo->getUserCar($user->id);
        if ($existingCar) {
            $this->setErrors(['error' => 'user_already_has_car', 'message' => 'User already has a car. Please update the existing one instead.']);

            return;
        }

        $v = $this->validator($data, $user->id);
        if ($v->fails()) {
            $this->setErrors($v->errors());

            return;
        } else {
            $car = new CarModel();
            $car->description = $data['description'];
            $car->patente = $data['patente'];
            $car->user_id = $user->id;
            $this->repo->create($car);

            return $car;
        }
    }

    public function update(UserModel $user, $id, $data)
    {
        $car = $this->show($user, $id);
        if ($car) {
            $v = $this->validator($data, $user->id, $id);
            if ($v->fails()) {
                $this->setErrors($v->errors());

                return;
            } else {
                $car->description = $data['description'];
                $car->patente = $data['patente'];
                $this->repo->update($car);

                return $car;
            }
        } else {
            $this->setErrors(['error' => 'car_not_found']);

            return;
        }
    }

    public function show(UserModel $user, $id)
    {
        $car = $this->repo->show($id);
        if ($car && $car->user_id == $user->id) {
            return $car;
        } else {
            $this->setErrors(['error' => 'car_not_found']);

            return;
        }
    }

    public function delete(UserModel $user, $id)
    {
        $car = $this->show($user, $id);
        if ($car) {
            if ($this->repo->delete($car)) {
                return true;
            } else {
                $this->setErrors(['error' => 'can_delete_car']);

                return;
            }
        } else {
            $this->setErrors(['error' => 'car_not_found']);

            return;
        }
    }

    public function index(UserModel $user)
    {
        return $this->repo->index($user);
    }
}
