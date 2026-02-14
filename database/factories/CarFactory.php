<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use STS\Models\Car;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\STS\Models\Car>
 */
class CarFactory extends Factory
{
    protected $model = \STS\Models\Car::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'patente'     => 'ASD 123',
            'description' => 'sandero',
        ];
    }
}
