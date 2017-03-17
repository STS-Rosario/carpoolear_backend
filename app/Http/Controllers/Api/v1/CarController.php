<?php

namespace STS\Http\Controllers\Api\v1;

use Auth;
use Illuminate\Http\Request;
use STS\Contracts\Logic\Car  as CarLogic;
use STS\Http\Controllers\Controller;

class CarController extends Controller
{
    protected $user;
    protected $carsLogic;

    public function __construct(Request $r, CarLogic $carsLogic)
    {
        $this->middleware('api.auth');
        $this->carsLogic = $carsLogic;
        $this->user = $this->auth->user();
    }

    public function create(Request $request)
    {
        $data = $request->all();
        $car = $this->carsLogic->create($this->user, $data);
        if (!$car) {
            throw new StoreResourceFailedException('Could not create new car.', $this->carsLogic->getErrors());
        }

        return $this->response->withArray(['data' => $car]);
    }

    public function update($id, Request $request)
    {
        $data = $request->all();
        $car = $this->carsLogic->update($this->user, $id, $data);
        if (!$car) {
            throw new StoreResourceFailedException('Could not update car.', $this->carsLogic->getErrors());
        }

        return $this->response->withArray(['data' => $car]);
    }

    public function delete($id, Request $request)
    {
        $result = $this->carsLogic->delete($this->user, $id);
        if (!$result) {
            throw new StoreResourceFailedException('Could not delete car.', $this->carsLogic->getErrors());
        }

        return $this->response->withArray(['data' => 'ok']);
    }

    public function show($id, Request $request)
    {
        $this->user = $this->auth->user();
        $car = $this->carsLogic->show($this->user, $id);
        if (!$car) {
            throw new ResourceException('Could not found car.', $this->carsLogic->getErrors());
        }

        return $this->response->withArray(['data' => $car]);
    }

    public function index(Request $request)
    {
        $cars = $this->carsLogic->index($this->user);

        return $cars;
    }
}
