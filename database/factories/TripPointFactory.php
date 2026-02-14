<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use STS\Models\TripPoint;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\STS\Models\TripPoint>
 */
class TripPointFactory extends Factory
{
    protected $model = \STS\Models\TripPoint::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'address'      => fake()->streetAddress(),
            'json_address' => ['city' => fake()->city()],
            'lat'          => fake()->latitude(),
            'lng'          => fake()->longitude(),
        ];
    }

    /**
     * Rosario, Santa Fe, Argentina.
     */
    public function rosario(): static
    {
        return $this->state(fn (array $attributes) => [
            'address'      => 'Rosario, Santa Fe, ARgentina',
            'json_address' => ['ciudad' => 'Rosario', 'provincia' => 'Santa Fe'],
            'lat'          => -32.946525,
            'lng'          => -60.669847,
            'sin_lat'      => sin(deg2rad(-32.946525)),
            'sin_lng'      => sin(deg2rad(-60.669847)),
            'cos_lat'      => cos(deg2rad(-32.946525)),
            'cos_lng'      => cos(deg2rad(-60.669847)),
        ]);
    }

    /**
     * Buenos Aires, Argentina.
     */
    public function buenosAires(): static
    {
        return $this->state(fn (array $attributes) => [
            'address'      => 'Buenos Aires, Argentina',
            'json_address' => ['ciudad' => 'Buenos Aires', 'provincia' => 'Buenos Aires'],
            'lat'          => -34.608903,
            'lng'          => -58.404521,
            'sin_lat'      => sin(deg2rad(-34.608903)),
            'sin_lng'      => sin(deg2rad(-58.404521)),
            'cos_lat'      => cos(deg2rad(-34.608903)),
            'cos_lng'      => cos(deg2rad(-58.404521)),
        ]);
    }

    /**
     * Cordoba, Cordoba, Argentina.
     */
    public function cordoba(): static
    {
        return $this->state(fn (array $attributes) => [
            'address'      => 'Cordoba, Cordoba, Argentina',
            'json_address' => ['ciudad' => 'Cordoba', 'provincia' => 'Cordoba'],
            'lat'          => -31.421045,
            'lng'          => -64.190543,
            'sin_lat'      => sin(deg2rad(-31.421045)),
            'sin_lng'      => sin(deg2rad(-64.190543)),
            'cos_lat'      => cos(deg2rad(-31.421045)),
            'cos_lng'      => cos(deg2rad(-64.190543)),
        ]);
    }

    /**
     * Mendoza, Mendoza, Argentina.
     */
    public function mendoza(): static
    {
        return $this->state(fn (array $attributes) => [
            'address'      => 'Mendoza, Mendoza, Argentina',
            'json_address' => ['ciudad' => 'Mendoza', 'provincia' => 'Mendoza'],
            'lat'          => -32.897273,
            'lng'          => -68.834067,
            'sin_lat'      => sin(deg2rad(-32.897273)),
            'sin_lng'      => sin(deg2rad(-68.834067)),
            'cos_lat'      => cos(deg2rad(-32.897273)),
            'cos_lng'      => cos(deg2rad(-68.834067)),
        ]);
    }
}
