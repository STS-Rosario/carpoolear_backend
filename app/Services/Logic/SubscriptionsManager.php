<?php

namespace STS\Services\Logic;

use Validator;
use STS\User as UserModel;
use STS\Entities\Subscription as SubscriptionModel;
use STS\Contracts\Logic\Car as SubscriptionLogic;
use STS\Contracts\Repository\Car as SubscriptionRepo;

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
            'from_address'      => 'required|string',
            'from_json_address' => 'required_with:from_address|array',
            'from_lat'          => 'required_with:from_address|numeric',
            'from_lng'          => 'required_with:from_address|numeric',
            'from_radio'        => 'required_with:from_address|numeric',

            'to_address'      => 'required|string',
            'to_json_address' => 'required_with:to_address|array',
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
            $this->repo->create($model);
            return $car;
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

    public function crateSearchData($subscription) 
    {
        
    }
}
