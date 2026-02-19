<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use STS\Models\Rating;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\STS\Models\Rating>
 */
class RatingFactory extends Factory
{
    protected $model = \STS\Models\Rating::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'comment' => fake()->sentence(),
            'rating'  => Rating::STATE_POSITIVO,
        ];
    }
}
