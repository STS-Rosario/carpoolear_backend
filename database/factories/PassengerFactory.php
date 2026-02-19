<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use STS\Models\Passenger;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\STS\Models\Passenger>
 */
class PassengerFactory extends Factory
{
    protected $model = \STS\Models\Passenger::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'request_state'  => Passenger::STATE_PENDING,
            'passenger_type' => Passenger::TYPE_PASAJERO,
        ];
    }

    /**
     * State for an accepted passenger.
     */
    public function aceptado(): static
    {
        return $this->state(fn (array $attributes) => [
            'request_state'  => Passenger::STATE_ACCEPTED,
            'passenger_type' => Passenger::TYPE_PASAJERO,
        ]);
    }
}
