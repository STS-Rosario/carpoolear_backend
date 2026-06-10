<?php

namespace STS\Http\Controllers\Api\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use STS\Http\Controllers\Controller;
use STS\Models\CarColor;

class CarColorController extends Controller
{
    public function index(): JsonResponse
    {
        $rows = CarColor::query()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return response()->json([
            'data' => $rows->map(fn (CarColor $color) => $this->serialize($color))->all(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $this->validatePayload($request);

        $color = CarColor::create([
            'name' => $validated['name'],
            'slug' => $this->uniqueSlug($validated['name']),
            'hex' => $validated['hex'] ?? null,
            'is_active' => $validated['is_active'] ?? true,
            'sort_order' => $validated['sort_order'] ?? 0,
        ]);

        return response()->json(['data' => $this->serialize($color)], Response::HTTP_CREATED);
    }

    public function show(CarColor $carColor): JsonResponse
    {
        return response()->json(['data' => $this->serialize($carColor)]);
    }

    public function update(Request $request, CarColor $carColor): JsonResponse
    {
        $validated = $this->validatePayload($request, $carColor->id);

        $carColor->fill([
            'name' => $validated['name'],
            'slug' => $this->uniqueSlug($validated['name'], $carColor->id),
            'hex' => $validated['hex'] ?? null,
            'is_active' => $validated['is_active'] ?? true,
            'sort_order' => $validated['sort_order'] ?? 0,
        ]);
        $carColor->save();

        return response()->json(['data' => $this->serialize($carColor)]);
    }

    public function destroy(CarColor $carColor): JsonResponse
    {
        if ($carColor->cars()->exists()) {
            $carColor->is_active = false;
            $carColor->save();

            return response()->json(['data' => $this->serialize($carColor)]);
        }

        $carColor->delete();

        return response()->json(['data' => 'ok']);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatePayload(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'hex' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:65535'],
        ]);
    }

    private function uniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name) ?: 'color';
        $slug = $base;
        $suffix = 1;

        while (CarColor::query()
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
    private function serialize(CarColor $color): array
    {
        return [
            'id' => $color->id,
            'name' => $color->name,
            'slug' => $color->slug,
            'hex' => $color->hex,
            'is_active' => $color->is_active,
            'sort_order' => $color->sort_order,
            'created_at' => $color->created_at?->toIso8601String(),
            'updated_at' => $color->updated_at?->toIso8601String(),
        ];
    }
}
