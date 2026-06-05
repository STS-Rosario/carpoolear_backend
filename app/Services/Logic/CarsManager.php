<?php

namespace STS\Services\Logic;

use Illuminate\Validation\Rule;
use STS\Models\Car as CarModel;
use STS\Models\User as UserModel;
use STS\Repository\CarsRepository;
use Validator;

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
            'patente' => ['required', 'string', 'max:10'],
            'description' => 'required|string|max:255',
        ];

        if ($userId) {
            $uniquePatente = Rule::unique('cars', 'patente')
                ->where(fn ($query) => $query->where('user_id', $userId)->whereNull('deleted_at'));

            if ($carId) {
                $uniquePatente->ignore($carId);
            }

            $rules['patente'][] = $uniquePatente;
        }

        return Validator::make($data, $rules);
    }

    public function create(UserModel $user, $data)
    {
        $v = $this->validator($data, $user->id);
        if ($v->fails()) {
            $this->setErrors($v->errors());

            return;
        } else {
            $existing = $this->repo->findByUserAndPatenteIncludingTrashed(
                $user->id,
                $data['patente']
            );

            if ($existing && $existing->trashed()) {
                $existing->restore();
                $existing->description = $data['description'];
                $this->repo->update($existing);

                return $existing;
            }

            $car = new CarModel;
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
