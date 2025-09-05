<?php

namespace STS\Http\Controllers\Api\Admin;

use STS\Http\Controllers\Controller;
use STS\Models\Car;
use STS\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;

class CarController extends Controller
{
    /**
     * Display a listing of all cars with user information.
     */
    public function index(): JsonResponse
    {
        $cars = Car::with('user:id,name,email')
            ->latest()
            ->get();

        return response()->json($cars);
    }

    /**
     * Display the specified car.
     */
    public function show(Car $car): JsonResponse
    {
        $car->load('user:id,name,email');
        return response()->json($car);
    }

    /**
     * Update the specified car in storage.
     */
    public function update(Request $request, Car $car): JsonResponse
    {
        $validated = $request->validate([
            'patente' => [
                'required',
                'string',
                'max:10',
                Rule::unique('cars')->ignore($car->id)
            ],
            'description' => 'required|string|max:255',
        ]);

        $car->update($validated);

        return response()->json($car->load('user:id,name,email'));
    }

    /**
     * Remove the specified car from storage.
     */
    public function destroy(Car $car): JsonResponse
    {
        $car->delete();
        return response()->json(null, 204);
    }

    /**
     * Get cars for a specific user.
     */
    public function userCars(User $user): JsonResponse
    {
        $cars = $user->cars()->latest()->get();
        return response()->json($cars);
    }

    /**
     * Create a car for a specific user (admin only).
     */
    public function storeForUser(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'patente' => [
                'required',
                'string',
                'max:10',
                Rule::unique('cars')->where(function ($query) use ($user) {
                    return $query->where('user_id', $user->id);
                })
            ],
            'description' => 'required|string|max:255',
        ]);

        $car = $user->cars()->create($validated);

        return response()->json($car->load('user:id,name,email'), 201);
    }
}
