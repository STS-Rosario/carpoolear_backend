<?php

namespace STS\Services\Logic;

use Validator;
use STS\User as UserModel;
use STS\Entities\Subscription as SubscriptionModel;
use STS\Contracts\Logic\Subscription as SubscriptionLogic;
use STS\Contracts\Repository\Subscription as SubscriptionRepo;

class SubscriptionsManager extends BaseManager implements SubscriptionLogic
{
    protected $repo;

    public function __construct(SubscriptionRepo $subscriptionsRepo)
    {
        $this->repo = $subscriptionsRepo;
    }

    public function validator(array $data)
    {
        return Validator::make($data, [
            'trip_date'     => 'date|after:now',
            'from_address'      => 'string',
            // 'from_json_address' => 'required_with:from_address|array',
            'from_lat'          => 'required_with:from_address|numeric',
            'from_lng'          => 'required_with:from_address|numeric',
            'from_radio'        => 'required_with:from_address|numeric',

            'to_address'      => 'string',
            // 'to_json_address' => 'required_with:to_address|array',
            'to_lat'          => 'required_with:to_address|numeric',
            'to_lng'          => 'required_with:to_address|numeric',
            'to_radio'        => 'required_with:to_address|numeric',
        ]);
    }

    public function create(UserModel $user, $data)
    {
        $v = $this->validator($data);
        if ($v->fails()) {
            $this->setErrors($v->errors());

            return;
        } else {
            $model = new SubscriptionModel();
            $model->fill($data);
            $model->state = true;
            if ($data['is_passenger'] === 'false') {
                $model->is_passenger = false;
            } else {
                $model->is_passenger = boolval($data['is_passenger']) ? true : false;
            }
            $model->user_id = $user->id;

            $ok = true;
            $userSuscriptions = $this->repo->list($user, true);
            if (count($userSuscriptions) > 0) {
                // trip_date
                // from_lat y from_lng
                // to_lat y to_lng
                // is_passenger
                foreach ($userSuscriptions as $s) {
                    $coincideFecha = false;
                    if (! empty($s->trip_date) && ! empty($model->trip_date)) {
                        $coincideFecha = ($s->trip_date == $model->trip_date);
                    } else {
                        if (empty($s->trip_date) && empty($model->trip_date)) {
                            $coincideFecha = true;
                        }
                    }
                    $coincideFrom = false;
                    if (! empty($s->from_lat) && ! empty($model->from_lat)) {
                        $coincideFrom = (strval($s->from_lat) == strval($model->from_lat) && strval($s->from_lng) == strval($model->from_lng));
                    } else {
                        if (empty($s->from_lat) && empty($model->from_lat)) {
                            $coincideFrom = true;
                        }
                    }
                    $coincideTo = false;
                    if (! empty($s->to_lat) && ! empty($model->to_lat)) {
                        $coincideTo = (strval($s->to_lat) == strval($model->to_lat) && strval($s->to_lng) == strval($model->to_lng));
                    } else {
                        if (empty($s->to_lat) && empty($model->to_lat)) {
                            $coincideTo = true;
                        }
                    }
                    $coincidePasajero = (boolval($s->is_passenger) == $model->is_passenger);

                    if ($coincideFecha && $coincideFrom && $coincideTo && $coincidePasajero) {
                        $ok = false;

                        break;
                    }
                }
            }
            if ($ok) {
                $this->repo->create($model);
            } else {
                $this->setErrors(['error' => 'subscription_exist']);

                return;
            }

            return $model;
        }
    }

    public function update(UserModel $user, $id, $data)
    {
        $model = $this->show($user, $id);
        if ($model) {
            $v = $this->validator($data);
            if ($v->fails()) {
                $this->setErrors($v->errors());

                return;
            } else {
                $model->fill($data);
                $this->repo->update($model);

                return $model;
            }
        } else {
            $this->setErrors(['error' => 'subscript_not_found']);

            return;
        }
    }

    public function show(UserModel $user, $id)
    {
        $model = $this->repo->show($id);
        if ($model && $model->user_id == $user->id) {
            return $model;
        } else {
            $this->setErrors(['error' => 'model_not_found']);

            return;
        }
    }

    public function delete(UserModel $user, $id)
    {
        $model = $this->show($user, $id);
        if ($model) {
            if ($this->repo->delete($model)) {
                return true;
            } else {
                $this->setErrors(['error' => 'cant_delete_model']);

                return;
            }
        } else {
            $this->setErrors(['error' => 'model_not_found']);

            return;
        }
    }

    public function index(UserModel $user)
    {
        return $this->repo->list($user, true);
    }

    public function syncTrip($trip)
    {
        $user = $trip->user;
    }
}
