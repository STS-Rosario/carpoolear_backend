<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use STS\Models\CarColor;

/**
 * @extends Factory<CarColor>
 */
class CarColorFactory extends Factory
{
    protected $model = CarColor::class;

    public function definition(): array
    {
        $name = fake()->unique()->colorName();

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'hex' => fake()->hexColor(),
            'is_active' => true,
            'sort_order' => fake()->numberBetween(0, 20),
        ];
    }
}
