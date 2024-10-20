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

    public function validator(array $data)
    {
        return Validator::make($data, [
            'patente'     => 'required|string',
            'description' => 'required|string',
        ]);
    }

    public function create(UserModel $user, $data)
    {
        $v = $this->validator($data);
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
            $v = $this->validator($data);
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
