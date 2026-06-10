<?php

namespace STS\Http\Controllers\Api\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use STS\Http\Controllers\Controller;
use STS\Models\CarBrand;
use STS\Models\CarModel;

class CarModelController extends Controller
{
    public function index(CarBrand $carBrand): JsonResponse
    {
        $rows = $carBrand->models()->orderBy('name')->get();

        return response()->json([
            'data' => $rows->map(fn (CarModel $model) => $this->serialize($model))->all(),
        ]);
    }

    public function store(Request $request, CarBrand $carBrand): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:150',
            'is_active' => 'sometimes|boolean',
            'argautos_id' => 'nullable|integer',
        ]);

        $model = CarModel::create([
            'car_brand_id' => $carBrand->id,
            'name' => $validated['name'],
            'slug' => $this->uniqueSlug($carBrand->id, $validated['name']),
            'argautos_id' => $validated['argautos_id'] ?? null,
            'is_active' => $validated['is_active'] ?? true,
        ]);

        return response()->json(['data' => $this->serialize($model)], Response::HTTP_CREATED);
    }

    public function show(CarBrand $carBrand, CarModel $carModel): JsonResponse
    {
        $this->assertModelBelongsToBrand($carBrand, $carModel);

        return response()->json(['data' => $this->serialize($carModel)]);
    }

    public function update(Request $request, CarBrand $carBrand, CarModel $carModel): JsonResponse
    {
        $this->assertModelBelongsToBrand($carBrand, $carModel);

        $validated = $request->validate([
            'name' => 'required|string|max:150',
            'is_active' => 'sometimes|boolean',
            'argautos_id' => 'nullable|integer',
        ]);

        $carModel->fill([
            'name' => $validated['name'],
            'slug' => $this->uniqueSlug($carBrand->id, $validated['name'], $carModel->id),
            'argautos_id' => $validated['argautos_id'] ?? $carModel->argautos_id,
            'is_active' => $validated['is_active'] ?? $carModel->is_active,
        ]);
        $carModel->save();

        return response()->json(['data' => $this->serialize($carModel)]);
    }

    public function destroy(CarBrand $carBrand, CarModel $carModel): JsonResponse
    {
        $this->assertModelBelongsToBrand($carBrand, $carModel);

        if ($carModel->cars()->exists()) {
            $carModel->is_active = false;
            $carModel->save();

            return response()->json(['data' => $this->serialize($carModel)]);
        }

        $carModel->delete();

        return response()->json(['data' => 'ok']);
    }

    private function assertModelBelongsToBrand(CarBrand $carBrand, CarModel $carModel): void
    {
        abort_if((int) $carModel->car_brand_id !== (int) $carBrand->id, 404);
    }

    private function uniqueSlug(int $brandId, string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name) ?: 'model';
        $slug = $base;
        $suffix = 1;

        while (CarModel::query()
            ->where('car_brand_id', $brandId)
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->where('slug', $slug)
            ->exists()) {
            $slug = $base.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(CarModel $model): array
    {
        return [
            'id' => $model->id,
            'car_brand_id' => $model->car_brand_id,
            'name' => $model->name,
            'slug' => $model->slug,
            'argautos_id' => $model->argautos_id,
            'is_active' => $model->is_active,
            'created_at' => $model->created_at?->toIso8601String(),
            'updated_at' => $model->updated_at?->toIso8601String(),
        ];
    }
}
