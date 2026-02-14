<?php

namespace Database\Factories;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;
use STS\Models\Trip;
use STS\Models\User;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\STS\Models\Trip>
 */
class TripFactory extends Factory
{
    protected $model = \STS\Models\Trip::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'is_passenger'       => 0,
            'from_town'          => fake()->streetAddress(),
            'to_town'            => fake()->streetAddress(),
            'trip_date'          => Carbon::now()->addHour(),
            'total_seats'        => 5,
            'friendship_type_id' => 2,
            'estimated_time'     => '05:00',
            'distance'           => 365,
            'co2'                => 50,
            'description'        => 'hola mundo',
            'mail_send'          => false,
            'user_id'            => User::factory(),
        ];
    }
}
