<?php

namespace STS\Http\Controllers\Api\v1;

use Illuminate\Http\Request;
use STS\Http\Controllers\Controller;
use STS\Services\Logic\SubscriptionsManager;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException; 

class SubscriptionController extends Controller
{
    protected $user;

    protected $subscriptionsLogic;

    public function __construct(SubscriptionsManager $subscriptionsLogic)
    {
        $this->middleware('logged');
        $this->subscriptionsLogic = $subscriptionsLogic;
    }

    public function create(Request $request)
    {
        $this->user = auth()->user();
        $data = $request->all();
        $model = $this->subscriptionsLogic->create($this->user, $data);
        if (! $model) {
            throw new BadRequestHttpException('Could not create new model.', $this->subscriptionsLogic->getErrors());
        }

        return response()->json(['data' => $model]);
    }

    public function update($id, Request $request)
    {
        $this->user = auth()->user();
        $data = $request->all();
        $model = $this->subscriptionsLogic->update($this->user, $id, $data);
        if (! $model) {
            throw new BadRequestHttpException('Could not update model.', $this->subscriptionsLogic->getErrors());
        }

        return response()->json(['data' => $model]);
    }

    public function delete($id, Request $request)
    {
        $this->user = auth()->user();
        $result = $this->subscriptionsLogic->delete($this->user, $id);
        if (! $result) {
            throw new BadRequestHttpException('Could not delete subscription.', $this->subscriptionsLogic->getErrors());
        }

        return response()->json(['data' => 'ok']);
    }

    public function show($id, Request $request)
    {
        $this->user = auth()->user();
        $model = $this->subscriptionsLogic->show($this->user, $id);
        if (! $model) {
            throw new BadRequestHttpException('Could not found model.', $this->subscriptionsLogic->getErrors());
        }

        return response()->json(['data' => $model]);
    }

    public function index(Request $request)
    {
        $this->user = auth()->user();
        $models = $this->subscriptionsLogic->index($this->user);

        return $models;
    }
}
