<?php

namespace STS\Http\Controllers\Api\v1;

use Illuminate\Http\Request;
use STS\Http\Controllers\Controller;
use STS\Contracts\Logic\Subscription  as SubscriptionLogic;
use Dingo\Api\Exception\StoreResourceFailedException;

class SubscriptionController extends Controller
{
    protected $user;
    protected $subscriptionsLogic;

    public function __construct(SubscriptionLogic $subscriptionsLogic)
    {
        $this->middleware('logged');
        $this->subscriptionsLogic = $subscriptionsLogic;
    }

    public function create(Request $request)
    {
        $this->user = $this->auth->user();
        $data = $request->all();
        $car = $this->subscriptionsLogic->create($this->user, $data);
        if (! $car) {
            throw new StoreResourceFailedException('Could not create new car.', $this->subscriptionsLogic->getErrors());
        }

        return $this->response->withArray(['data' => $car]);
    }

    public function update($id, Request $request)
    {
        $this->user = $this->auth->user();
        $data = $request->all();
        $car = $this->subscriptionsLogic->update($this->user, $id, $data);
        if (! $car) {
            throw new StoreResourceFailedException('Could not update car.', $this->subscriptionsLogic->getErrors());
        }

        return $this->response->withArray(['data' => $car]);
    }

    public function delete($id, Request $request)
    {
        $this->user = $this->auth->user();
        $result = $this->subscriptionsLogic->delete($this->user, $id);
        if (! $result) {
            throw new StoreResourceFailedException('Could not delete car.', $this->subscriptionsLogic->getErrors());
        }

        return $this->response->withArray(['data' => 'ok']);
    }

    public function show($id, Request $request)
    {
        $this->user = $this->auth->user();
        $car = $this->subscriptionsLogic->show($this->user, $id);
        if (! $car) {
            throw new StoreResourceFailedException('Could not found car.', $this->subscriptionsLogic->getErrors());
        }

        return $this->response->withArray(['data' => $car]);
    }

    public function index(Request $request)
    {
        $this->user = $this->auth->user();
        $cars = $this->subscriptionsLogic->index($this->user);

        return $cars;
    }
}
