<?php

namespace STS\Http\Controllers\Api\v1;

use Illuminate\Http\JsonResponse;
use STS\Http\Controllers\Controller;
use STS\Models\CarBrand;
use STS\Models\CarColor;
use STS\Models\CarModel;

class CarCatalogController extends Controller
{
    public function brands(): JsonResponse
    {
        $rows = CarBrand::query()->active()->orderBy('name')->get();

        return response()->json([
            'data' => $rows->map(fn (CarBrand $brand) => [
                'id' => $brand->id,
                'name' => $brand->name,
                'slug' => $brand->slug,
            ])->all(),
        ]);
    }

    public function models(CarBrand $carBrand): JsonResponse
    {
        $rows = $carBrand->models()->active()->orderBy('name')->get();

        return response()->json([
            'data' => $rows->map(fn (CarModel $model) => [
                'id' => $model->id,
                'car_brand_id' => $model->car_brand_id,
                'name' => $model->name,
                'slug' => $model->slug,
            ])->all(),
        ]);
    }

    public function colors(): JsonResponse
    {
        $rows = CarColor::query()->active()->get();

        return response()->json([
            'data' => $rows->map(fn (CarColor $color) => [
                'id' => $color->id,
                'name' => $color->name,
                'slug' => $color->slug,
            ])->all(),
        ]);
    }
}
