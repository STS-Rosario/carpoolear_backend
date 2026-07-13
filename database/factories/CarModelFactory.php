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
            // Leave null so factories never collide with seeded catalog argautos_ids.
            'argautos_id' => null,
            'is_active' => true,
        ];
    }
}
