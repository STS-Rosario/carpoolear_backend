<?php

namespace STS\Http\Controllers\Api\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use STS\Http\Controllers\Controller;
use STS\Models\CarBrand;

class CarBrandController extends Controller
{
    public function index(): JsonResponse
    {
        $rows = CarBrand::query()->orderBy('name')->get();

        return response()->json([
            'data' => $rows->map(fn (CarBrand $brand) => $this->serialize($brand))->all(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'is_active' => 'sometimes|boolean',
            'argautos_id' => 'nullable|integer|unique:car_brands,argautos_id',
        ]);

        $brand = CarBrand::create([
            'name' => $validated['name'],
            'slug' => $this->uniqueSlug($validated['name']),
            'argautos_id' => $validated['argautos_id'] ?? null,
            'is_active' => $validated['is_active'] ?? true,
        ]);

        return response()->json(['data' => $this->serialize($brand)], Response::HTTP_CREATED);
    }

    public function show(CarBrand $carBrand): JsonResponse
    {
        return response()->json(['data' => $this->serialize($carBrand)]);
    }

    public function update(Request $request, CarBrand $carBrand): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'is_active' => 'sometimes|boolean',
            'argautos_id' => 'nullable|integer|unique:car_brands,argautos_id,'.$carBrand->id,
        ]);

        $carBrand->fill([
            'name' => $validated['name'],
            'slug' => $this->uniqueSlug($validated['name'], $carBrand->id),
            'argautos_id' => $validated['argautos_id'] ?? $carBrand->argautos_id,
            'is_active' => $validated['is_active'] ?? $carBrand->is_active,
        ]);
        $carBrand->save();

        return response()->json(['data' => $this->serialize($carBrand)]);
    }

    public function destroy(CarBrand $carBrand): JsonResponse
    {
        if ($carBrand->cars()->exists()) {
            $carBrand->is_active = false;
            $carBrand->save();

            return response()->json(['data' => $this->serialize($carBrand)]);
        }

        $carBrand->delete();

        return response()->json(['data' => 'ok']);
    }

    private function uniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name) ?: 'brand';
        $slug = $base;
        $suffix = 1;

        while (CarBrand::query()
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
    private function serialize(CarBrand $brand): array
    {
        return [
            'id' => $brand->id,
            'name' => $brand->name,
            'slug' => $brand->slug,
            'argautos_id' => $brand->argautos_id,
            'is_active' => $brand->is_active,
            'created_at' => $brand->created_at?->toIso8601String(),
            'updated_at' => $brand->updated_at?->toIso8601String(),
        ];
    }
}
