<?php

namespace Database\Factories;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;
use STS\Models\Subscription;
use STS\Models\User;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\STS\Models\Subscription>
 */
class SubscriptionFactory extends Factory
{
    protected $model = \STS\Models\Subscription::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'state'        => true,
            'trip_date'    => Carbon::now(),
            'is_passenger' => false,
            'user_id'      => User::factory(),
        ];
    }
}
