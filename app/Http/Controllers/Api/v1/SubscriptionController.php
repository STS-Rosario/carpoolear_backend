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
        $model = $this->subscriptionsLogic->create($this->user, $data); 
        if (! $model) {
            throw new StoreResourceFailedException('Could not create new model.', $this->subscriptionsLogic->getErrors());
        }

        return $this->response->withArray(['data' => $model]);
    }

    public function update($id, Request $request)
    {
        $this->user = $this->auth->user();
        $data = $request->all();
        $model = $this->subscriptionsLogic->update($this->user, $id, $data);
        if (! $model) {
            throw new StoreResourceFailedException('Could not update model.', $this->subscriptionsLogic->getErrors());
        }

        return $this->response->withArray(['data' => $model]);
    }

    public function delete($id, Request $request)
    {
        $this->user = $this->auth->user();
        $result = $this->subscriptionsLogic->delete($this->user, $id);
        if (! $result) {
            throw new StoreResourceFailedException('Could not delete subscription.', $this->subscriptionsLogic->getErrors());
        }

        return $this->response->withArray(['data' => 'ok']);
    }

    public function show($id, Request $request)
    {
        $this->user = $this->auth->user();
        $model = $this->subscriptionsLogic->show($this->user, $id);
        if (! $model) {
            throw new StoreResourceFailedException('Could not found model.', $this->subscriptionsLogic->getErrors());
        }

        return $this->response->withArray(['data' => $model]);
    }

    public function index(Request $request)
    {
        $this->user = $this->auth->user();
        $models = $this->subscriptionsLogic->index($this->user);

        return $models;
    }
}
