<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use STS\Models\Car;
use STS\Models\CarBrand;
use STS\Models\CarColor;
use STS\Models\CarModel;

/**
 * @extends Factory<Car>
 */
class CarFactory extends Factory
{
    protected $model = Car::class;

    public function definition(): array
    {
        return [
            'patente' => 'ASD 123',
            'description' => 'sandero',
        ];
    }

    public function withCatalog(): static
    {
        return $this->state(function () {
            $brand = CarBrand::factory()->create();
            $model = CarModel::factory()->create(['car_brand_id' => $brand->id]);
            $color = CarColor::factory()->create();

            return [
                'car_brand_id' => $brand->id,
                'car_model_id' => $model->id,
                'car_color_id' => $color->id,
            ];
        });
    }
}
