<?php

namespace STS\Http\Controllers\Api\v1;

use Illuminate\Http\Request;
use STS\Http\Controllers\Controller;  
use STS\Services\Logic\CarsManager;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class CarController extends Controller
{
    protected $user;

    protected $carsLogic;

    public function __construct(CarsManager $carsLogic)
    {
        $this->middleware('logged');
        $this->carsLogic = $carsLogic;
    }

    public function create(Request $request)
    {
        $this->user = auth()->user();
        $data = $request->all();
        $car = $this->carsLogic->create($this->user, $data);
        if (! $car) {
            throw new BadRequestHttpException('Could not create new car.', $this->carsLogic->getErrors());
        }

        return response()->json(['data' => $car]);
    }

    public function update($id, Request $request)
    {
        $this->user = auth()->user();
        $data = $request->all();
        $car = $this->carsLogic->update($this->user, $id, $data);
        if (! $car) {
            throw new BadRequestHttpException('Could not update car.', $this->carsLogic->getErrors());
        }

        return response()->json(['data' => $car]);
    }

    public function delete($id, Request $request)
    {
        $this->user = auth()->user();
        $result = $this->carsLogic->delete($this->user, $id);
        if (! $result) {
            throw new BadRequestHttpException('Could not delete car.', $this->carsLogic->getErrors());
        }

        return response()->json(['data' => 'ok']);
    }

    public function show($id, Request $request)
    {
        $this->user = auth()->user();
        $car = $this->carsLogic->show($this->user, $id);
        if (! $car) {
            throw new BadRequestHttpException('Could not found car.', $this->carsLogic->getErrors());
        }

        return response()->json(['data' => $car]);
    }

    public function index(Request $request)
    {
        $this->user = auth()->user();
        $cars = $this->carsLogic->index($this->user);

        return $cars;
    }
}
