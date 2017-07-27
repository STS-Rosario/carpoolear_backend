<?php

namespace STS\Http\Controllers\Api\v1;

use Illuminate\Http\Request;
use STS\Http\Controllers\Controller;
use STS\Contracts\Logic\Car  as CarLogic;
use Dingo\Api\Exception\StoreResourceFailedException;

class CarController extends Controller
{
    protected $user;
    protected $carsLogic;

    public function __construct(CarLogic $carsLogic)
    {
        $this->middleware('logged');
        $this->carsLogic = $carsLogic;
    }

    public function create(Request $request)
    {
        $this->user = $this->auth->user();
        $data = $request->all();
        $car = $this->carsLogic->create($this->user, $data);
        if (! $car) {
            throw new StoreResourceFailedException('Could not create new car.', $this->carsLogic->getErrors());
        }

        return $this->response->withArray(['data' => $car]);
    }

    public function update($id, Request $request)
    {
        $this->user = $this->auth->user();
        $data = $request->all();
        $car = $this->carsLogic->update($this->user, $id, $data);
        if (! $car) {
            throw new StoreResourceFailedException('Could not update car.', $this->carsLogic->getErrors());
        }

        return $this->response->withArray(['data' => $car]);
    }

    public function delete($id, Request $request)
    {
        $this->user = $this->auth->user();
        $result = $this->carsLogic->delete($this->user, $id);
        if (! $result) {
            throw new StoreResourceFailedException('Could not delete car.', $this->carsLogic->getErrors());
        }

        return $this->response->withArray(['data' => 'ok']);
    }

    public function show($id, Request $request)
    {
        $this->user = $this->auth->user();
        $car = $this->carsLogic->show($this->user, $id);
        if (! $car) {
            throw new StoreResourceFailedException('Could not found car.', $this->carsLogic->getErrors());
        }

        return $this->response->withArray(['data' => $car]);
    }

    public function index(Request $request)
    {
        $this->user = $this->auth->user();
        $cars = $this->carsLogic->index($this->user);

        return $cars;
    }
}
