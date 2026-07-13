<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use STS\Models\CarBrand;

/**
 * @extends Factory<CarBrand>
 */
class CarBrandFactory extends Factory
{
    protected $model = CarBrand::class;

    public function definition(): array
    {
        $name = fake()->unique()->company();

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            // Leave null so factories never collide with seeded catalog argautos_ids.
            // Faker unique() only tracks values it generated, not existing DB rows.
            'argautos_id' => null,
            'is_active' => true,
        ];
    }
}
