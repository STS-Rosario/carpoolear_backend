<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use STS\Models\CarBrand;
use STS\Models\CarModel;

/**
 * @extends Factory<CarModel>
 */
class CarModelFactory extends Factory
{
    protected $model = CarModel::class;

    public function definition(): array
    {
        $name = fake()->unique()->word();

        return [
            'car_brand_id' => CarBrand::factory(),
            'name' => strtoupper($name),
            'slug' => Str::slug($name),
            'argautos_id' => fake()->unique()->numberBetween(1, 99999),
            'is_active' => true,
        ];
    }
}
